<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\Program;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;

class WorkoutPresenter
{
    public static function exercise(Exercise $e, bool $withSubs = false): array
    {
        $out = [
            'id' => $e->id,
            'slug' => $e->slug,
            'name_en' => $e->name_en,
            'name_ru' => $e->name_ru,
            'muscles' => $e->muscles ?? [],
            'equipment' => $e->equipment ?? \App\Support\Equipment::guess($e->slug),
            'video_url' => $e->video_url,
            'custom' => $e->user_id !== null,
        ];
        if ($withSubs) {
            $out['substitutes'] = $e->substitutes->map(fn (Exercise $s) => [
                ...self::exercise($s),
                'note_en' => $s->pivot->note_en,
                'note_ru' => $s->pivot->note_ru,
            ])->values();
        }

        return $out;
    }

    public static function programTree(Program $program): array
    {
        $program->load('weeks.days.exercises.exercise');
        // Days with workouts can't be removed in the builder.
        $usedDayIds = \App\Models\WorkoutSession::whereIn(
            'program_day_id', $program->weeks->flatMap(fn ($w) => $w->days->pluck('id'))
        )->pluck('program_day_id')->flip();

        return [
            ...self::programSummary($program),
            'weeks' => $program->weeks->map(fn ($w) => [
                'id' => $w->id,
                'week_no' => $w->week_no,
                'block' => $w->block,
                'days' => $w->days->map(fn ($d) => [
                    'id' => $d->id,
                    'day_no' => $d->day_no,
                    'title_en' => $d->title_en,
                    'title_ru' => $d->title_ru,
                    'used' => $usedDayIds->has($d->id),
                    'exercises' => $d->exercises->map(fn ($x) => self::slot($x))->values(),
                ])->values(),
            ])->values(),
        ];
    }

    public static function programSummary(Program $p): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'name_en' => $p->name_en,
            'name_ru' => $p->name_ru,
            'description_en' => $p->description_en,
            'description_ru' => $p->description_ru,
            'days_per_week' => $p->days_per_week,
            'weeks_count' => $p->weeks_count ?? $p->weeks()->count(),
            'visibility' => $p->visibility,
            'source' => $p->source,
            'is_mine' => $p->user_id !== null && $p->user_id === auth()->id(),
            // template = shared ready-made; library = user's imported ready-made (private); custom = built/copied by the user
            'kind' => $p->user_id === null ? 'template' : ($p->source === 'import' ? 'library' : 'custom'),
        ];
    }

    public static function slot($x, bool $withSubs = false): array
    {
        return [
            'id' => $x->id,
            'position' => $x->position,
            'sets' => $x->sets,
            'warmup_sets' => $x->warmup_sets,
            'reps' => $x->reps,
            'reps_max' => $x->reps_max,
            'reps_text' => $x->reps_text,
            'seconds' => $x->seconds,
            'target_rpe' => $x->target_rpe,
            'load_pct' => $x->load_pct,
            'rest_min' => $x->rest_min,
            'rest_max' => $x->rest_max,
            'notes_en' => $x->notes_en,
            'notes_ru' => $x->notes_ru,
            'exercise' => self::exercise($x->exercise, $withSubs),
        ];
    }

    public static function set(SetLog $s): array
    {
        return [
            'id' => $s->id,
            'day_exercise_id' => $s->day_exercise_id,
            'exercise_id' => $s->exercise_id,
            'set_no' => $s->set_no,
            'is_warmup' => $s->is_warmup,
            'weight_kg' => $s->weight_kg,
            'reps' => $s->reps,
            'seconds' => $s->seconds,
            'rpe' => $s->rpe,
        ];
    }

    /** Sets from the most recent other session where the user did this exercise. */
    public static function lastPerformance(User $user, int $exerciseId, ?int $excludeSessionId = null): ?array
    {
        $sessionId = SetLog::query()
            ->where('exercise_id', $exerciseId)
            ->where('is_warmup', false)
            ->whereHas('session', fn ($q) => $q->where('user_id', $user->id))
            ->when($excludeSessionId, fn ($q) => $q->where('workout_session_id', '!=', $excludeSessionId))
            ->orderByDesc('workout_session_id')
            ->value('workout_session_id');

        if (! $sessionId) {
            return null;
        }
        $session = WorkoutSession::find($sessionId);

        return [
            'session_id' => $sessionId,
            'date' => $session->started_at->toDateString(),
            'sets' => SetLog::where('workout_session_id', $sessionId)
                ->where('exercise_id', $exerciseId)->where('is_warmup', false)
                ->orderBy('set_no')->get()->map(fn ($s) => self::set($s))->values(),
        ];
    }

    public static function session(WorkoutSession $session, User $user): array
    {
        $session->load(['day.week', 'day.exercises.exercise.substitutes', 'sets']);
        $slots = $session->day?->exercises ?? collect();

        $exerciseIds = $slots->pluck('exercise_id')
            ->merge($slots->flatMap(fn ($x) => $x->exercise->substitutes->pluck('id')))
            ->merge($session->sets->pluck('exercise_id'))
            ->unique();

        $advisor = new LoadAdvisor($user, $session->id);
        $previous = [];
        foreach ($exerciseIds as $id) {
            $previous[$id] = self::lastPerformance($user, $id, $session->id);
        }

        return [
            'id' => $session->id,
            'status' => $session->status,
            'started_at' => $session->started_at->toIso8601String(),
            'finished_at' => $session->finished_at?->toIso8601String(),
            'note' => $session->note,
            'skipped_slots' => $session->skipped_slots ?? [],
            'day' => $session->day ? [
                'id' => $session->day->id,
                'week_no' => $session->day->week->week_no,
                'block' => $session->day->week->block,
                'day_no' => $session->day->day_no,
                'title_en' => $session->day->title_en,
                'title_ru' => $session->day->title_ru,
            ] : null,
            'slots' => $slots->map(function ($x) use ($advisor) {
                $out = self::slot($x, true);
                // Today's load suggestion for the planned exercise and each substitute.
                $out['advice'] = (object) collect([$x->exercise, ...$x->exercise->substitutes])
                    ->mapWithKeys(fn ($e) => [$e->id => $advisor->advise($x, $e)])
                    ->filter()->all();

                return $out;
            })->values(),
            'sets' => $session->sets->map(fn ($s) => self::set($s))->values(),
            'previous' => (object) array_filter($previous),
        ];
    }

    public static function sessionSummary(WorkoutSession $s): array
    {
        $work = $s->sets->where('is_warmup', false);

        return [
            'id' => $s->id,
            'status' => $s->status,
            'started_at' => $s->started_at->toIso8601String(),
            'finished_at' => $s->finished_at?->toIso8601String(),
            'duration_min' => $s->finished_at && $s->status === WorkoutSession::FINISHED
                ? (int) round($s->started_at->diffInMinutes($s->finished_at)) : null,
            'day' => $s->day ? [
                'week_no' => $s->day->week->week_no,
                'title_en' => $s->day->title_en,
                'title_ru' => $s->day->title_ru,
            ] : null,
            'note' => $s->note,
            'sets_count' => $work->count(),
            'volume_kg' => round($work->sum(fn ($x) => ($x->weight_kg ?? 0) * ($x->reps ?? 0))),
        ];
    }
}
