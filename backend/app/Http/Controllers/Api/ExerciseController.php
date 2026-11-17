<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Services\WorkoutPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExerciseController extends Controller
{
    public const MUSCLES = ['quads', 'hamstrings', 'glutes', 'chest', 'back', 'lats', 'shoulders', 'biceps', 'triceps', 'abs', 'calves'];

    public function index(Request $request)
    {
        return Exercise::visibleTo($request->user())
            ->with('substitutes')
            ->orderBy('name_en')
            ->get()
            ->map(fn ($e) => WorkoutPresenter::exercise($e, true));
    }

    /** Create the user's own exercise (for the program builder). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name_ru' => ['nullable', 'string', 'max:150', 'required_without:name_en'],
            'name_en' => ['nullable', 'string', 'max:150', 'required_without:name_ru'],
            'muscles' => ['nullable', 'array'],
            'muscles.*' => ['string', Rule::in(self::MUSCLES)],
            'equipment' => ['nullable', Rule::in(['barbell', 'dumbbell', 'machine', 'cable', 'bodyweight', 'other'])],
        ]);
        $nameEn = ($data['name_en'] ?? null) ?: $data['name_ru'];
        $base = Str::slug($nameEn) ?: 'exercise';
        $slug = $base;
        for ($i = 2; Exercise::where('user_id', $request->user()->id)->where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        $exercise = Exercise::create([
            'user_id' => $request->user()->id,
            'slug' => $slug,
            'name_en' => $nameEn,
            'name_ru' => $data['name_ru'] ?? null,
            'muscles' => $data['muscles'] ?? [],
            'equipment' => $data['equipment'] ?? \App\Support\Equipment::guess($slug),
        ]);

        return response()->json(WorkoutPresenter::exercise($exercise->load('substitutes'), true), 201);
    }

    public function history(Request $request, Exercise $exercise)
    {
        $this->ensureVisible($request, $exercise);

        $sets = SetLog::query()
            ->with('session')
            ->where('exercise_id', $exercise->id)
            ->where('is_warmup', false)
            ->whereHas('session', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderByDesc('workout_session_id')->orderBy('set_no')
            ->limit(1000)
            ->get()
            ->groupBy('workout_session_id');

        $entries = $sets->map(function ($group) {
            $session = $group->first()->session;

            return [
                'session_id' => $session->id,
                'date' => $session->started_at->toDateString(),
                'sets' => $group->map(fn ($s) => WorkoutPresenter::set($s))->values(),
                'best_e1rm' => $group->map(fn ($s) => SetLog::e1rm($s->weight_kg, $s->reps, $s->rpe))->filter()->max(),
                'top_weight' => $group->max('weight_kg'),
                'volume_kg' => round($group->sum(fn ($s) => ($s->weight_kg ?? 0) * ($s->reps ?? 0))),
            ];
        })->values();

        return response()->json([
            'exercise' => WorkoutPresenter::exercise($exercise),
            'entries' => $entries,
            'best_e1rm' => $entries->max('best_e1rm'),
        ]);
    }

    /** Personal records across all exercises. */
    public function records(Request $request)
    {
        return response()->json(\App\Services\Records::summary($request->user()));
    }

    private function ensureVisible(Request $request, Exercise $exercise): void
    {
        abort_if($exercise->user_id !== null && $exercise->user_id !== $request->user()->id, 404);
    }
}
