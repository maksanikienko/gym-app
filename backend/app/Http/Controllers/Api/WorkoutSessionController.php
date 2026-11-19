<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DayExercise;
use App\Models\WorkoutSession;
use App\Services\CycleProgress;
use App\Services\WorkoutPresenter;
use Illuminate\Http\Request;

class WorkoutSessionController extends Controller
{
    public function index(Request $request)
    {
        WorkoutSession::closeStale($request->user()->id);
        $page = $request->user()->workoutSessions()
            ->with(['day.week', 'sets'])
            ->latest('started_at')->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn ($s) => WorkoutPresenter::sessionSummary($s)),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    /**
     * Open the workout for a program day of the active cycle.
     * Existing workout → returned as is (one per day, no duplicates).
     * New workout → only for the next day in order; earlier days must be finished or skipped.
     */
    public function store(Request $request)
    {
        $data = $request->validate(['program_day_id' => ['required', 'integer']]);
        $user = $request->user();
        WorkoutSession::closeStale($user->id);
        $cycle = $user->activeCycle()->with('program')->firstOrFail();
        $progress = new CycleProgress($cycle);

        $day = $progress->days->firstWhere('id', $data['program_day_id']);
        abort_unless($day, 404);

        if ($existing = $progress->sessions->get($day->id)) {
            return response()->json(WorkoutPresenter::session($existing, $user));
        }

        abort_unless(
            $progress->nextDay()?->id === $day->id,
            422,
            __('Finish or annul the previous workouts first.')
        );

        $session = WorkoutSession::create([
            'user_id' => $user->id,
            'cycle_id' => $cycle->id,
            'program_day_id' => $day->id,
            'status' => WorkoutSession::IN_PROGRESS,
            'started_at' => now(),
        ]);

        return response()->json(WorkoutPresenter::session($session, $user), 201);
    }

    public function show(Request $request, WorkoutSession $session)
    {
        $this->authorizeSession($request, $session);
        WorkoutSession::closeStale($request->user()->id);

        return response()->json(WorkoutPresenter::session($session->refresh(), $request->user()));
    }

    public function update(Request $request, WorkoutSession $session)
    {
        $this->authorizeSession($request, $session);
        $data = $request->validate([
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Full list of skipped exercises (slot ids of this workout's day); logged sets are kept.
            'skipped_slots' => ['sometimes', 'array', 'max:100'],
            'skipped_slots.*' => ['integer'],
        ]);

        if (array_key_exists('skipped_slots', $data)) {
            $ids = array_values(array_unique(array_map('intval', $data['skipped_slots'])));
            $valid = DayExercise::whereIn('id', $ids)->where('program_day_id', $session->program_day_id)->count();
            abort_unless($valid === count($ids), 422, 'Invalid skipped_slots');
            sort($ids);
            $data['skipped_slots'] = $ids;
        }
        $session->update($data);

        return response()->json(['note' => $session->note, 'skipped_slots' => $session->skipped_slots ?? []]);
    }

    public function finish(Request $request, WorkoutSession $session)
    {
        $this->authorizeSession($request, $session);
        $session->update([
            'status' => WorkoutSession::FINISHED,
            'finished_at' => $session->finished_at ?? now(),
        ]);

        return response()->json(WorkoutPresenter::sessionSummary($session->load(['day.week', 'sets'])));
    }

    /** Annul: the day counts as closed (unlocks the next one) but wasn't trained. */
    public function skip(Request $request, WorkoutSession $session)
    {
        $this->authorizeSession($request, $session);
        $session->update(['status' => WorkoutSession::SKIPPED, 'finished_at' => now()]);

        return response()->json(WorkoutPresenter::sessionSummary($session->load(['day.week', 'sets'])));
    }

    /** Delete the workout and its sets; the day becomes open again. */
    public function destroy(Request $request, WorkoutSession $session)
    {
        $this->authorizeSession($request, $session);
        $session->delete();

        return response()->noContent();
    }

    private function authorizeSession(Request $request, WorkoutSession $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 404);
    }
}
