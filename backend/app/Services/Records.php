<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;

/** Personal records per exercise: heaviest weight, best estimated 1RM, most reps at a weight. */
class Records
{
    /**
     * Records this set beats compared with all of the user's other sets of the exercise.
     * Nothing is reported the very first time an exercise is done.
     *
     * @return array<int, array{type: string, value: float|int, previous: float|int|null}>
     */
    public static function brokenBy(SetLog $set, User $user): array
    {
        if ($set->is_warmup || ! $set->reps || ! $set->weight_kg) {
            return [];
        }
        $others = SetLog::query()
            ->where('exercise_id', $set->exercise_id)->where('is_warmup', false)
            ->whereKeyNot($set->id)->where('reps', '>', 0)
            ->whereHas('session', fn ($q) => $q->where('user_id', $user->id))
            ->get(['weight_kg', 'reps', 'rpe']);
        if ($others->isEmpty()) {
            return [];
        }

        $found = [];
        $maxWeight = (float) $others->max('weight_kg');
        if ($set->weight_kg > $maxWeight) {
            $found[] = ['type' => 'weight', 'value' => $set->weight_kg, 'previous' => $maxWeight];
        }

        $e1rm = SetLog::e1rm($set->weight_kg, $set->reps, $set->rpe);
        $bestE1rm = $others->map(fn ($s) => SetLog::e1rm($s->weight_kg, $s->reps, $s->rpe))->filter()->max();
        if ($e1rm && $set->reps <= 15 && $e1rm > (float) $bestE1rm) {
            $found[] = ['type' => 'e1rm', 'value' => $e1rm, 'previous' => $bestE1rm];
        }

        // More reps than ever with this weight or heavier (only when it isn't already a weight record)
        if ($set->weight_kg <= $maxWeight) {
            $repsAtLeast = (int) $others->filter(fn ($s) => $s->weight_kg >= $set->weight_kg)->max('reps');
            if ($set->reps > $repsAtLeast) {
                $found[] = ['type' => 'reps', 'value' => $set->reps, 'previous' => $repsAtLeast];
            }
        }

        return $found;
    }

    /** Best results for every exercise the user has logged. */
    public static function summary(User $user): array
    {
        $sets = SetLog::query()
            ->with(['session:id,started_at,user_id', 'exercise'])
            ->where('is_warmup', false)->where('reps', '>', 0)->where('weight_kg', '>', 0)
            ->whereHas('session', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        return $sets->groupBy('exercise_id')->map(function ($group) {
            $heaviest = $group->sortBy([['weight_kg', 'desc'], ['reps', 'desc']])->first();
            $best = $group->filter(fn ($s) => $s->reps <= 15)
                ->sortByDesc(fn ($s) => SetLog::e1rm($s->weight_kg, $s->reps, $s->rpe))->first();
            /** @var Exercise $exercise */
            $exercise = $group->first()->exercise;

            return [
                'exercise' => WorkoutPresenter::exercise($exercise),
                'heaviest' => self::point($heaviest),
                'best_e1rm' => $best ? self::point($best) + ['e1rm' => SetLog::e1rm($best->weight_kg, $best->reps, $best->rpe)] : null,
                'last_date' => $group->max(fn ($s) => $s->session->started_at)->toDateString(),
                'sessions' => $group->pluck('workout_session_id')->unique()->count(),
            ];
        })->sortByDesc('last_date')->values()->all();
    }

    private static function point(SetLog $s): array
    {
        return [
            'weight_kg' => $s->weight_kg, 'reps' => $s->reps, 'rpe' => $s->rpe,
            'date' => $s->session->started_at->toDateString(), 'session_id' => $s->workout_session_id,
        ];
    }
}
