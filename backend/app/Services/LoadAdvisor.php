<?php

namespace App\Services;

use App\Models\DayExercise;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Support\Equipment;

/**
 * Suggests today's working load for a program slot, based on the last time the user
 * did the exercise and on their estimated 1RM.
 *
 * Rules (simple, explainable autoregulation):
 *  - %1RM prescription            → weight = e1RM × %.
 *  - Same rep scheme as last time:
 *      all reps done, last set ≥1 RPE easier than target  → +1 step ("was easy")
 *      top of the rep range reached on every set          → +1 step, aim for the bottom of the range
 *      all reps done, no RPE logged                       → +1 step (linear progression)
 *      all reps done, RPE at target                       → keep the weight
 *      clearly too hard (reps missed by 2+ or RPE ≥ target+1.5) → −~5%
 *      otherwise                                          → keep the weight, finish the reps
 *  - Different rep scheme → convert through e1RM to today's reps at the target RPE.
 *  - Never done → explain how to find a starting weight.
 *
 * Returns a structure the frontend renders with i18n: basis + params.
 */
class LoadAdvisor
{
    private const DEFAULT_RPE = 8.0;
    private const RECENT_DAYS = 120;

    public function __construct(private User $user, private ?int $excludeSessionId = null)
    {
    }

    public function advise(DayExercise $slot, Exercise $exercise): ?array
    {
        $equipment = $exercise->equipment ?? Equipment::guess($exercise->slug);
        $timed = $slot->reps === null && $slot->seconds !== null;
        $repsMin = $slot->reps;
        $repsTop = $slot->reps_max ?? $slot->reps;
        $targetRpe = $slot->target_rpe ?? ($slot->load_pct ? null : self::DEFAULT_RPE);

        $last = $this->lastSession($exercise->id);
        $e1rm = $this->estimatedMax($exercise->id, $last);

        // --- timed holds: progress the duration, not the load
        if ($timed) {
            if (! $last) {
                return $this->result('first_timed', null, null, ['seconds' => $slot->seconds], $last, $e1rm);
            }
            $best = (int) collect($last['sets'])->max('seconds');
            $easy = $this->lastRpe($last) !== null && $this->lastRpe($last) <= ($targetRpe ?? self::DEFAULT_RPE) - 1;
            $secs = $best >= (int) $slot->seconds && $easy ? $best + 5 : max($best, (int) $slot->seconds);

            return $this->result($secs > $best ? 'longer' : 'hold_time', null, null, ['seconds' => $secs], $last, $e1rm, $secs);
        }

        // --- bodyweight movements without added load
        if ($equipment === Equipment::BODYWEIGHT && (! $last || ! $last['top']['weight_kg'])) {
            if ($last && $repsTop && collect($last['sets'])->every(fn ($s) => ($s['reps'] ?? 0) >= $repsTop)) {
                return $this->result('bodyweight_add', null, $repsTop, ['reps' => $repsTop], $last, $e1rm);
            }

            return $this->result('bodyweight', null, $repsMin, ['reps' => $repsMin], $last, $e1rm);
        }

        // --- %1RM prescriptions
        if ($slot->load_pct) {
            if ($e1rm) {
                $w = Equipment::round($e1rm * $slot->load_pct / 100, $equipment);

                return $this->result('pct', $w, $repsMin, ['pct' => $slot->load_pct, 'e1rm' => $e1rm], $last, $e1rm);
            }
            $repsAtPct = (int) max(1, round(30 * (100 / $slot->load_pct - 1)));

            return $this->result('pct_unknown', null, $repsMin, ['pct' => $slot->load_pct, 'reps' => $repsAtPct], $last, $e1rm);
        }

        $rpe = $targetRpe ?? self::DEFAULT_RPE;

        // --- never done
        if (! $last || ! $last['top']['weight_kg']) {
            if ($e1rm && $repsMin) {
                $w = $this->weightFor($e1rm, $repsMin, $rpe, $equipment);

                return $this->result('e1rm', $w, $repsMin, ['e1rm' => $e1rm, 'reps' => $repsMin, 'rpe' => $rpe], $last, $e1rm);
            }

            return $this->result('first', null, $repsMin, ['rir' => max(0, (int) round(10 - $rpe)), 'rpe' => $rpe], $last, $e1rm);
        }

        $top = $last['top'];
        $w0 = (float) $top['weight_kg'];
        $step = Equipment::step($equipment, $w0);
        $lastReps = (int) ($top['reps'] ?? 0);

        // Different rep scheme than last time → convert through the estimated max.
        if ($repsMin && abs($lastReps - $repsMin) > 2 && ! ($slot->reps_max && $lastReps >= $repsMin && $lastReps <= $slot->reps_max)) {
            $w = $this->weightFor($e1rm ?? SetLog::e1rm($w0, $lastReps, $top['rpe']), $repsMin, $rpe, $equipment);

            return $this->result('convert', $w, $repsMin, [
                'last_weight' => $w0, 'last_reps' => $lastReps, 'reps' => $repsMin, 'rpe' => $rpe,
            ], $last, $e1rm);
        }

        $work = array_values(array_filter($last['sets'], fn ($s) => (float) ($s['weight_kg'] ?? 0) >= $w0 - 0.01));
        $hitTop = $repsTop && collect($work)->every(fn ($s) => ($s['reps'] ?? 0) >= $repsTop);
        $hitMin = ! $repsMin || collect($work)->every(fn ($s) => ($s['reps'] ?? 0) >= $repsMin);
        $missedBy = $repsMin ? max(0, $repsMin - (int) collect($work)->min('reps')) : 0;
        $lastRpe = $this->lastRpe($last);
        $delta = $lastRpe !== null ? $lastRpe - $rpe : null;

        $p = ['last_weight' => $w0, 'last_reps' => $lastReps, 'last_rpe' => $lastRpe, 'rpe' => $rpe, 'step' => $step];

        if ($hitTop && $delta !== null && $delta <= -1) {
            return $this->result('increase_easy', Equipment::round($w0 + $step, $equipment), $repsMin, $p, $last, $e1rm);
        }
        if ($hitTop && $slot->reps_max && ($delta === null || $delta <= 0.5)) {
            return $this->result('increase_range', Equipment::round($w0 + $step, $equipment), $repsMin, $p + ['reps' => $repsMin], $last, $e1rm);
        }
        if ($hitTop && $delta === null) {
            return $this->result('increase_linear', Equipment::round($w0 + $step, $equipment), $repsMin, $p, $last, $e1rm);
        }
        if ($missedBy >= 2 || ($delta !== null && $delta >= 1.5)) {
            // About −5%, at least one load step.
            $w = Equipment::round($w0 * 0.95, $equipment);
            if ($w >= $w0) {
                $w = Equipment::round($w0 - $step, $equipment);
            }

            return $this->result('decrease', min($w, $w0), $repsMin, $p, $last, $e1rm);
        }
        if ($hitMin && $slot->reps_max) {
            // Double progression: bring the weakest set up by one rep.
            $aim = min($slot->reps_max, (int) collect($work)->min('reps') + 1);

            return $this->result('add_reps', $w0, $aim, $p + ['reps' => $aim], $last, $e1rm);
        }
        if ($hitMin) {
            return $this->result('hold_target', $w0, $repsMin, $p, $last, $e1rm);
        }

        return $this->result('hold', $w0, $repsMin, $p + ['reps' => $repsMin], $last, $e1rm);
    }

    /** Weight for N reps at a given RPE from an estimated max (Epley with reps in reserve). */
    private function weightFor(?float $e1rm, int $reps, float $rpe, ?string $equipment): ?float
    {
        if (! $e1rm) {
            return null;
        }
        $rir = max(0, 10 - $rpe);

        return Equipment::round($e1rm / (1 + ($reps + $rir) / 30), $equipment, 'down');
    }

    private function lastRpe(array $last): ?float
    {
        $withRpe = array_values(array_filter($last['sets'], fn ($s) => $s['rpe'] !== null));

        return $withRpe ? (float) end($withRpe)['rpe'] : null;
    }

    /** Last session (other than the current one) where the exercise was done. */
    private function lastSession(int $exerciseId): ?array
    {
        $perf = WorkoutPresenter::lastPerformance($this->user, $exerciseId, $this->excludeSessionId);
        if (! $perf || ! count($perf['sets'])) {
            return null;
        }
        $sets = collect($perf['sets'])->map(fn ($s) => (array) $s)->all();
        $top = collect($sets)->sortByDesc(fn ($s) => [(float) ($s['weight_kg'] ?? 0), (int) ($s['reps'] ?? 0)])->first();

        return ['date' => $perf['date'], 'sets' => $sets, 'top' => $top];
    }

    /** Estimated 1RM: last session's best if available, else the best of recent history. */
    private function estimatedMax(int $exerciseId, ?array $last): ?float
    {
        $fromLast = $last ? collect($last['sets'])
            ->filter(fn ($s) => ($s['reps'] ?? 0) > 0 && ($s['reps'] ?? 0) <= 15)
            ->map(fn ($s) => SetLog::e1rm($s['weight_kg'], $s['reps'], $s['rpe']))->filter()->max() : null;
        if ($fromLast) {
            return $fromLast;
        }

        return SetLog::query()
            ->where('exercise_id', $exerciseId)->where('is_warmup', false)
            ->whereBetween('reps', [1, 15])->where('weight_kg', '>', 0)
            ->whereHas('session', fn ($q) => $q->where('user_id', $this->user->id)
                ->where('started_at', '>=', now()->subDays(self::RECENT_DAYS))
                ->when($this->excludeSessionId, fn ($q) => $q->whereKeyNot($this->excludeSessionId)))
            ->get()
            ->map(fn ($s) => SetLog::e1rm($s->weight_kg, $s->reps, $s->rpe))->filter()->max();
    }

    private function result(string $basis, ?float $weight, ?int $reps, array $params, ?array $last, ?float $e1rm, ?int $seconds = null): array
    {
        return [
            'basis' => $basis,
            'weight_kg' => $weight,
            'reps' => $reps,
            'seconds' => $seconds,
            'params' => array_filter($params, fn ($v) => $v !== null),
            'e1rm' => $e1rm ? round($e1rm, 1) : null,
            'last_date' => $last['date'] ?? null,
        ];
    }
}
