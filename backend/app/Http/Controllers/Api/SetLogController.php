<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DayExercise;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Services\Records;
use App\Services\WorkoutPresenter;
use Illuminate\Http\Request;

class SetLogController extends Controller
{
    public function store(Request $request, WorkoutSession $session)
    {
        abort_unless($session->user_id === $request->user()->id, 404);
        $data = $request->validate($this->rules() + [
            'day_exercise_id' => ['nullable', 'integer'],
            'exercise_id' => ['required', 'integer'],
            'set_no' => ['required', 'integer', 'between:1,50'],
        ]);

        $exercise = Exercise::visibleTo($request->user())->findOrFail($data['exercise_id']);
        if (! empty($data['day_exercise_id'])) {
            abort_unless(
                DayExercise::whereKey($data['day_exercise_id'])->where('program_day_id', $session->program_day_id)->exists(),
                422, 'Invalid day_exercise_id'
            );
        }

        // Idempotent per (slot, exercise, set_no): re-saving a set updates it.
        $set = SetLog::updateOrCreate(
            [
                'workout_session_id' => $session->id,
                'day_exercise_id' => $data['day_exercise_id'] ?? null,
                'exercise_id' => $exercise->id,
                'set_no' => $data['set_no'],
                'is_warmup' => $data['is_warmup'] ?? false,
            ],
            collect($data)->only(['weight_kg', 'reps', 'seconds', 'rpe'])->all()
        );

        return response()->json(WorkoutPresenter::set($set) + ['records' => Records::brokenBy($set, $request->user())], 201);
    }

    public function update(Request $request, SetLog $set)
    {
        $this->authorizeSet($request, $set);
        $set->update($request->validate($this->rules()));

        return response()->json(WorkoutPresenter::set($set) + ['records' => Records::brokenBy($set, $request->user())]);
    }

    public function destroy(Request $request, SetLog $set)
    {
        $this->authorizeSet($request, $set);
        $set->delete();

        return response()->noContent();
    }

    private function rules(): array
    {
        return [
            'is_warmup' => ['sometimes', 'boolean'],
            'weight_kg' => ['nullable', 'numeric', 'between:0,1000'],
            'reps' => ['nullable', 'integer', 'between:0,500'],
            'seconds' => ['nullable', 'integer', 'between:0,3600'],
            'rpe' => ['nullable', 'numeric', 'between:1,10'],
        ];
    }

    private function authorizeSet(Request $request, SetLog $set): void
    {
        abort_unless($set->session->user_id === $request->user()->id, 404);
    }
}
