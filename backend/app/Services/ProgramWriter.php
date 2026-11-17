<?php

namespace App\Services;

use App\Models\DayExercise;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\ProgramWeek;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates/updates a program from the builder's full tree.
 *
 * Existing weeks/days/exercises are matched by id and updated in place, so
 * workout history (which points at program days and slots) stays attached.
 * Days that already have workouts cannot be removed.
 */
class ProgramWriter
{
    public static function rules(): array
    {
        return [
            'name_ru' => ['nullable', 'string', 'max:150', 'required_without:name_en'],
            'name_en' => ['nullable', 'string', 'max:150', 'required_without:name_ru'],
            'description_ru' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'weeks' => ['required', 'array', 'min:1', 'max:52'],
            'weeks.*.id' => ['nullable', 'integer'],
            'weeks.*.block' => ['nullable', 'string', 'max:32'],
            'weeks.*.days' => ['required', 'array', 'min:1', 'max:7'],
            'weeks.*.days.*.id' => ['nullable', 'integer'],
            'weeks.*.days.*.title_ru' => ['nullable', 'string', 'max:150'],
            'weeks.*.days.*.title_en' => ['nullable', 'string', 'max:150'],
            'weeks.*.days.*.exercises' => ['required', 'array', 'min:1', 'max:30'],
            'weeks.*.days.*.exercises.*.id' => ['nullable', 'integer'],
            'weeks.*.days.*.exercises.*.exercise_id' => ['required', 'integer'],
            'weeks.*.days.*.exercises.*.sets' => ['required', 'integer', 'between:1,20'],
            'weeks.*.days.*.exercises.*.reps' => ['nullable', 'integer', 'between:1,200'],
            'weeks.*.days.*.exercises.*.reps_max' => ['nullable', 'integer', 'between:1,200'],
            'weeks.*.days.*.exercises.*.reps_text' => ['nullable', 'string', 'max:40'],
            'weeks.*.days.*.exercises.*.warmup_sets' => ['nullable', 'integer', 'between:0,10'],
            'weeks.*.days.*.exercises.*.load_pct' => ['nullable', 'numeric', 'between:1,110'],
            'weeks.*.days.*.exercises.*.seconds' => ['nullable', 'integer', 'between:1,3600'],
            'weeks.*.days.*.exercises.*.target_rpe' => ['nullable', 'numeric', 'between:1,10'],
            'weeks.*.days.*.exercises.*.rest_min' => ['nullable', 'integer', 'between:0,15'],
            'weeks.*.days.*.exercises.*.rest_max' => ['nullable', 'integer', 'between:0,15'],
            'weeks.*.days.*.exercises.*.notes_ru' => ['nullable', 'string', 'max:1000'],
            'weeks.*.days.*.exercises.*.notes_en' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** $user = null saves a system program (ready-made template) that uses only the shared catalogue. */
    public function save(Program $program, array $data, ?User $user): Program
    {
        return DB::transaction(function () use ($program, $data, $user) {
            $this->assertExercisesVisible($data, $user);

            $nameEn = $data['name_en'] ?? null ?: $data['name_ru'];
            $program->fill([
                'user_id' => $user?->id,
                'name_en' => $nameEn,
                'name_ru' => $data['name_ru'] ?? null,
                'description_en' => $data['description_en'] ?? null,
                'description_ru' => $data['description_ru'] ?? null,
                'days_per_week' => max(array_map(fn ($w) => count($w['days']), $data['weeks'])),
                'slug' => $data['slug'] ?? $program->slug ?? (Str::slug($nameEn) ?: 'program'),
                'visibility' => $program->visibility ?? 'private',
                'source' => $program->source ?? 'user',
            ])->save();

            $existingWeeks = $program->weeks()->with('days.exercises')->get()->keyBy('id');
            // Shift numbers first so renumbering never collides with unique indexes.
            ProgramWeek::where('program_id', $program->id)->update(['week_no' => DB::raw('week_no + 1000')]);

            $keepWeeks = [];
            foreach (array_values($data['weeks']) as $wi => $w) {
                $week = $existingWeeks->get($w['id'] ?? 0) ?? new ProgramWeek(['program_id' => $program->id]);
                $week->fill(['week_no' => $wi + 1, 'block' => $w['block'] ?? null])->save();
                $keepWeeks[] = $week->id;
                $this->syncDays($week, $w['days']);
            }

            $removed = $existingWeeks->keys()->diff($keepWeeks);
            if ($removed->isNotEmpty()) {
                $dayIds = ProgramDay::whereIn('program_week_id', $removed)->pluck('id');
                $this->assertUnused($dayIds->all());
                ProgramWeek::whereIn('id', $removed)->delete();
            }

            return $program->refresh();
        });
    }

    private function syncDays(ProgramWeek $week, array $days): void
    {
        $existing = $week->exists ? $week->days()->with('exercises')->get()->keyBy('id') : collect();
        ProgramDay::where('program_week_id', $week->id)->update(['day_no' => DB::raw('day_no + 100')]);

        $keep = [];
        foreach (array_values($days) as $di => $d) {
            $day = $existing->get($d['id'] ?? 0) ?? new ProgramDay(['program_week_id' => $week->id]);
            $titleRu = $d['title_ru'] ?? null;
            $day->fill([
                'day_no' => $di + 1,
                'title_ru' => $titleRu,
                'title_en' => ($d['title_en'] ?? null) ?: ($titleRu ?: 'Day '.($di + 1)),
            ])->save();
            $keep[] = $day->id;
            $this->syncExercises($day, $d['exercises']);
        }

        $removed = $existing->keys()->diff($keep)->all();
        if ($removed) {
            $this->assertUnused($removed);
            ProgramDay::whereIn('id', $removed)->delete();
        }
    }

    private function syncExercises(ProgramDay $day, array $rows): void
    {
        $existing = $day->exercises()->get()->keyBy('id');
        $keep = [];
        foreach (array_values($rows) as $i => $x) {
            $slot = $existing->get($x['id'] ?? 0) ?? new DayExercise(['program_day_id' => $day->id]);
            $restMin = $x['rest_min'] ?? null;
            $restMax = $x['rest_max'] ?? $restMin;
            $slot->fill([
                'exercise_id' => $x['exercise_id'],
                'position' => $i + 1,
                'sets' => $x['sets'],
                'reps' => $x['reps'] ?? null,
                'reps_max' => ($x['reps'] ?? null) && ($x['reps_max'] ?? null) > $x['reps'] ? $x['reps_max'] : null,
                'reps_text' => $x['reps_text'] ?? null,
                'seconds' => ($x['reps'] ?? null) ? null : ($x['seconds'] ?? null),
                'warmup_sets' => $x['warmup_sets'] ?? null,
                'target_rpe' => $x['target_rpe'] ?? null,
                'load_pct' => $x['load_pct'] ?? null,
                'rest_min' => $restMin !== null && $restMax !== null ? min($restMin, $restMax) : $restMin,
                'rest_max' => $restMin !== null && $restMax !== null ? max($restMin, $restMax) : $restMax,
                'notes_ru' => $x['notes_ru'] ?? null,
                'notes_en' => $x['notes_en'] ?? null,
            ])->save();
            $keep[] = $slot->id;
        }
        DayExercise::where('program_day_id', $day->id)->whereNotIn('id', $keep)->delete();
    }

    private function assertExercisesVisible(array $data, ?User $user): void
    {
        $ids = collect($data['weeks'])->flatMap(fn ($w) => collect($w['days'])
            ->flatMap(fn ($d) => array_column($d['exercises'], 'exercise_id')))->unique();
        $visible = ($user ? Exercise::visibleTo($user) : Exercise::whereNull('user_id'))->whereIn('id', $ids)->pluck('id');
        if ($ids->diff($visible)->isNotEmpty()) {
            throw ValidationException::withMessages(['weeks' => __('Unknown exercise.')]);
        }
    }

    private function assertUnused(array $dayIds): void
    {
        if ($dayIds && WorkoutSession::whereIn('program_day_id', $dayIds)->exists()) {
            throw ValidationException::withMessages([
                'weeks' => __('Days that already have workouts cannot be removed.'),
            ]);
        }
    }

    /** The builder tree of an existing program, without ids (for copies). */
    public static function exportTree(Program $program): array
    {
        $program->load('weeks.days.exercises');

        return [
            'name_en' => $program->name_en,
            'name_ru' => $program->name_ru,
            'description_en' => $program->description_en,
            'description_ru' => $program->description_ru,
            'weeks' => $program->weeks->map(fn ($w) => [
                'block' => $w->block,
                'days' => $w->days->map(fn ($d) => [
                    'title_en' => $d->title_en,
                    'title_ru' => $d->title_ru,
                    'exercises' => $d->exercises->map(fn ($x) => $x->only([
                        'exercise_id', 'sets', 'warmup_sets', 'reps', 'reps_max', 'reps_text', 'seconds',
                        'target_rpe', 'load_pct', 'rest_min', 'rest_max', 'notes_en', 'notes_ru',
                    ]))->all(),
                ])->all(),
            ])->all(),
        ];
    }
}
