<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\DayExercise;
use App\Models\SetLog;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;

/** Results of a whole cycle (program run): attendance, volume, strength progress, skipped exercises. */
class CycleSummary
{
    /** How many exercises to list in the progress block. */
    private const PROGRESS_LIMIT = 6;

    public static function build(Cycle $cycle): array
    {
        $cycle->loadMissing('program');
        /** @var Collection<int, WorkoutSession> $sessions */
        $sessions = $cycle->sessions()->with('sets.exercise')->orderBy('started_at')->orderBy('id')->get();
        $finished = $sessions->where('status', WorkoutSession::FINISHED);
        $work = $finished->flatMap(fn (WorkoutSession $s) => $s->sets)->where('is_warmup', false);

        return [
            'cycle_id' => $cycle->id,
            'program' => WorkoutPresenter::programSummary($cycle->program->loadCount('weeks')),
            'started_at' => $cycle->started_at->toIso8601String(),
            'finished_at' => ($cycle->finished_at ?? $sessions->max('finished_at'))?->toIso8601String(),
            'workouts' => [
                'finished' => $finished->count(),
                'skipped' => $sessions->where('status', WorkoutSession::SKIPPED)->count(),
                'total' => $cycle->program->orderedDays()->count(),
            ],
            'training_minutes' => (int) $finished
                ->filter(fn ($s) => $s->finished_at)
                ->sum(fn ($s) => $s->started_at->diffInMinutes($s->finished_at)),
            'sets_count' => $work->count(),
            'volume_kg' => (int) round($work->sum(fn ($s) => ($s->weight_kg ?? 0) * ($s->reps ?? 0))),
            'progress' => self::progress($finished),
            'skipped_exercises' => self::skippedExercises($sessions),
        ];
    }

    /**
     * Strength change per exercise: best estimated 1RM in the first workout with it vs the best later in the cycle.
     * Only exercises done with weight in at least two workouts; most frequent first.
     */
    private static function progress(Collection $finished): array
    {
        $bySession = [];
        foreach ($finished as $session) {
            foreach ($session->sets as $set) {
                if ($set->is_warmup || ! $set->weight_kg || ! $set->reps || $set->reps > 15) {
                    continue;
                }
                $e1rm = SetLog::e1rm($set->weight_kg, $set->reps, $set->rpe);
                $best = &$bySession[$set->exercise_id][$session->id];
                $best = max($best ?? 0, $e1rm);
                unset($best);
            }
        }

        $exercises = $finished->flatMap(fn ($s) => $s->sets)->pluck('exercise', 'exercise_id');

        return collect($bySession)
            ->filter(fn ($perSession) => count($perSession) >= 2)
            ->map(function ($perSession, $exerciseId) use ($exercises) {
                $values = array_values($perSession); // sessions are in chronological order
                $start = $values[0];
                $peak = max(array_slice($values, 1));

                return [
                    'exercise' => WorkoutPresenter::exercise($exercises[$exerciseId]),
                    'sessions' => count($values),
                    'start_e1rm' => $start,
                    'best_e1rm' => $peak,
                    'change_pct' => $start > 0 ? round(($peak - $start) / $start * 100, 1) : null,
                ];
            })
            ->sortBy([['sessions', 'desc'], ['change_pct', 'desc']])
            ->take(self::PROGRESS_LIMIT)
            ->values()
            ->all();
    }

    /** Exercises the user chose to skip, most often first. */
    private static function skippedExercises(Collection $sessions): array
    {
        $slotIds = $sessions->flatMap(fn ($s) => $s->skipped_slots ?? []);
        if ($slotIds->isEmpty()) {
            return [];
        }
        $slots = DayExercise::with('exercise')->whereIn('id', $slotIds->unique())->get()->keyBy('id');

        return $slotIds
            ->map(fn ($id) => $slots->get($id)?->exercise)
            ->filter()
            ->groupBy('id')
            ->map(fn ($group) => ['exercise' => WorkoutPresenter::exercise($group->first()), 'times' => $group->count()])
            ->sortByDesc('times')
            ->take(5)
            ->values()
            ->all();
    }
}
