<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Models\Program;
use App\Models\WorkoutSession;
use App\Services\CycleProgress;
use App\Services\CycleSummary;
use App\Services\WorkoutPresenter;
use Illuminate\Http\Request;

class CycleController extends Controller
{
    /** Active cycle with progress, per-day status and the next day to train. */
    public function current(Request $request)
    {
        WorkoutSession::closeStale($request->user()->id);
        $cycle = $request->user()->activeCycle()->with('program')->first();
        if (! $cycle) {
            return response()->noContent(); // no active cycle
        }

        $progress = new CycleProgress($cycle, withExercises: true);
        $next = $progress->nextDay();
        $open = $progress->sessions->firstWhere('status', WorkoutSession::IN_PROGRESS);
        $openDay = $open ? $progress->days->firstWhere('id', $open->program_day_id) : null;

        return response()->json([
            'id' => $cycle->id,
            'started_at' => $cycle->started_at->toIso8601String(),
            'program' => WorkoutPresenter::programSummary($cycle->program->loadCount('weeks')),
            'progress' => ['done' => $progress->closedCount(), 'total' => $progress->days->count()],
            'open_session' => $open ? [
                'id' => $open->id,
                'started_at' => $open->started_at->toIso8601String(),
                'week_no' => $openDay?->week_no,
                'title_en' => $openDay?->title_en,
                'title_ru' => $openDay?->title_ru,
                'sets_count' => $open->sets()->count(),
            ] : null,
            'next_day_id' => $next?->id,
            'days' => $progress->days->map(fn ($d) => [
                'id' => $d->id,
                'week_no' => $d->week_no,
                'day_no' => $d->day_no,
                'title_en' => $d->title_en,
                'title_ru' => $d->title_ru,
                'status' => $progress->status($d->id),
                'session_id' => $progress->sessions->get($d->id)?->id,
                'exercises' => $d->exercises->map(fn ($x) => [
                    'name_en' => $x->exercise->name_en,
                    'name_ru' => $x->exercise->name_ru,
                    'sets' => $x->sets,
                    'reps' => $x->reps,
                    'reps_max' => $x->reps_max,
                    'reps_text' => $x->reps_text,
                    'seconds' => $x->seconds,
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['program_id' => ['required', 'integer', 'exists:programs,id']]);
        $program = Program::findOrFail($data['program_id']);
        abort_unless($program->isVisibleTo($request->user()), 404);

        Cycle::where('user_id', $request->user()->id)->whereNull('finished_at')
            ->update(['finished_at' => now()]);

        $cycle = Cycle::create([
            'user_id' => $request->user()->id,
            'program_id' => $program->id,
            'started_at' => now(),
        ]);

        return response()->json(['id' => $cycle->id], 201);
    }

    /** Annul the next day without training it. */
    public function skipNext(Request $request, Cycle $cycle)
    {
        abort_unless($cycle->user_id === $request->user()->id && ! $cycle->finished_at, 404);
        $next = (new CycleProgress($cycle))->nextDay();
        abort_unless($next, 422, __('All workouts are already closed.'));

        $session = WorkoutSession::updateOrCreate(
            ['cycle_id' => $cycle->id, 'program_day_id' => $next->id],
            ['user_id' => $cycle->user_id, 'status' => WorkoutSession::SKIPPED, 'started_at' => now(), 'finished_at' => now()],
        );

        return response()->json(['id' => $session->id]);
    }

    /** Results of the cycle — shown when all its days are closed, and for finished cycles. */
    public function summary(Request $request, Cycle $cycle)
    {
        abort_unless($cycle->user_id === $request->user()->id, 404);
        WorkoutSession::closeStale($request->user()->id);

        return response()->json(CycleSummary::build($cycle));
    }

    public function finish(Request $request, Cycle $cycle)
    {
        abort_unless($cycle->user_id === $request->user()->id, 404);
        $cycle->update(['finished_at' => now()]);

        return response()->noContent();
    }
}
