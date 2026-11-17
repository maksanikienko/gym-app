<?php

namespace App\Services;

use App\Models\DayExercise;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\ProgramWeek;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Imports a "program pack" JSON (format gym-app.program-pack.v1).
 *
 * $owner = null → shared/system content (used by seeders);
 * otherwise everything is created as the user's private data.
 */
class ProgramImporter
{
    public const FORMAT = 'gym-app.program-pack.v1';

    public static function rules(): array
    {
        return [
            'format' => ['required', 'in:'.self::FORMAT],
            'exercises' => ['array', 'max:500'],
            'exercises.*.slug' => ['required', 'string', 'max:100'],
            'exercises.*.name_en' => ['required', 'string', 'max:150'],
            'exercises.*.name_ru' => ['nullable', 'string', 'max:150'],
            'exercises.*.muscles' => ['nullable', 'array'],
            'exercises.*.video_url' => ['nullable', 'url', 'max:255'],
            'programs' => ['required', 'array', 'min:1', 'max:20'],
            'programs.*.slug' => ['required', 'string', 'max:100'],
            'programs.*.name_en' => ['required', 'string', 'max:150'],
            'programs.*.name_ru' => ['nullable', 'string', 'max:150'],
            'programs.*.description_en' => ['nullable', 'string', 'max:5000'],
            'programs.*.description_ru' => ['nullable', 'string', 'max:5000'],
            'programs.*.days_per_week' => ['required', 'integer', 'between:1,7'],
            'programs.*.weeks' => ['required', 'array', 'min:1', 'max:52'],
            'programs.*.weeks.*.week_no' => ['required', 'integer', 'between:1,52'],
            'programs.*.weeks.*.block' => ['nullable', 'string', 'max:32'],
            'programs.*.weeks.*.days' => ['required', 'array', 'min:1', 'max:7'],
            'programs.*.weeks.*.days.*.day_no' => ['required', 'integer', 'between:1,7'],
            'programs.*.weeks.*.days.*.title_en' => ['required', 'string', 'max:150'],
            'programs.*.weeks.*.days.*.title_ru' => ['nullable', 'string', 'max:150'],
            'programs.*.weeks.*.days.*.exercises' => ['required', 'array', 'min:1', 'max:30'],
            'programs.*.weeks.*.days.*.exercises.*.exercise' => ['required', 'string', 'max:100'],
            'programs.*.weeks.*.days.*.exercises.*.sets' => ['required', 'integer', 'between:1,20'],
            'programs.*.weeks.*.days.*.exercises.*.reps' => ['nullable', 'integer', 'between:1,200'],
            'programs.*.weeks.*.days.*.exercises.*.seconds' => ['nullable', 'integer', 'between:1,3600'],
            'programs.*.weeks.*.days.*.exercises.*.target_rpe' => ['nullable', 'numeric', 'between:1,10'],
            'programs.*.weeks.*.days.*.exercises.*.warmup_sets' => ['nullable', 'integer', 'between:0,10'],
            'programs.*.weeks.*.days.*.exercises.*.reps_max' => ['nullable', 'integer', 'between:1,200'],
            'programs.*.weeks.*.days.*.exercises.*.reps_text' => ['nullable', 'string', 'max:40'],
            'programs.*.weeks.*.days.*.exercises.*.load_pct' => ['nullable', 'numeric', 'between:1,110'],
            'programs.*.weeks.*.days.*.exercises.*.rest_min' => ['nullable', 'integer', 'between:0,15'],
            'programs.*.weeks.*.days.*.exercises.*.rest_max' => ['nullable', 'integer', 'between:0,15'],
            'programs.*.weeks.*.days.*.exercises.*.notes_en' => ['nullable', 'string', 'max:1000'],
            'programs.*.weeks.*.days.*.exercises.*.notes_ru' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return Program[] */
    public function import(array $pack, ?User $owner, string $visibility = 'private', string $source = 'import'): array
    {
        $pack = Validator::make($pack, self::rules())->validate();

        return DB::transaction(function () use ($pack, $owner, $visibility, $source) {
            $exerciseIds = $this->resolveExercises($pack, $owner);

            $created = [];
            foreach ($pack['programs'] as $p) {
                $program = Program::create([
                    'user_id' => $owner?->id,
                    'slug' => Str::slug($p['slug']),
                    'name_en' => $p['name_en'],
                    'name_ru' => $p['name_ru'] ?? null,
                    'description_en' => $p['description_en'] ?? null,
                    'description_ru' => $p['description_ru'] ?? null,
                    'days_per_week' => $p['days_per_week'],
                    'visibility' => $visibility,
                    'source' => $source,
                ]);

                foreach ($p['weeks'] as $w) {
                    $week = ProgramWeek::create([
                        'program_id' => $program->id,
                        'week_no' => $w['week_no'],
                        'block' => $w['block'] ?? null,
                    ]);
                    foreach ($w['days'] as $d) {
                        $day = ProgramDay::create([
                            'program_week_id' => $week->id,
                            'day_no' => $d['day_no'],
                            'title_en' => $d['title_en'],
                            'title_ru' => $d['title_ru'] ?? null,
                        ]);
                        foreach (array_values($d['exercises']) as $i => $e) {
                            $exerciseId = $exerciseIds[$e['exercise']]
                                ?? $this->findOrCreateExercise($e['exercise'], null, $owner);
                            DayExercise::create([
                                'program_day_id' => $day->id,
                                'exercise_id' => $exerciseId,
                                'position' => $i + 1,
                                'sets' => $e['sets'],
                                'reps' => $e['reps'] ?? null,
                                'seconds' => $e['seconds'] ?? null,
                                'target_rpe' => $e['target_rpe'] ?? null,
                                'warmup_sets' => $e['warmup_sets'] ?? null,
                                'reps_max' => $e['reps_max'] ?? null,
                                'reps_text' => $e['reps_text'] ?? null,
                                'load_pct' => $e['load_pct'] ?? null,
                                'rest_min' => $e['rest_min'] ?? null,
                                'rest_max' => $e['rest_max'] ?? ($e['rest_min'] ?? null),
                                'notes_en' => $e['notes_en'] ?? null,
                                'notes_ru' => $e['notes_ru'] ?? null,
                            ]);
                        }
                    }
                }
                $created[] = $program;
            }

            return $created;
        });
    }

    /** @return array<string,int> slug → exercise id */
    private function resolveExercises(array $pack, ?User $owner): array
    {
        $map = [];
        foreach ($pack['exercises'] ?? [] as $e) {
            $map[$e['slug']] = $this->findOrCreateExercise($e['slug'], $e, $owner);
        }

        return $map;
    }

    private function findOrCreateExercise(string $slug, ?array $data, ?User $owner): int
    {
        $slug = Str::slug($slug);
        // Prefer the shared catalogue; fall back to the owner's own exercises.
        $existing = Exercise::whereNull('user_id')->where('slug', $slug)->first()
            ?? ($owner ? Exercise::where('user_id', $owner->id)->where('slug', $slug)->first() : null);

        if ($existing) {
            if ($data && ! empty($data['video_url']) && $existing->user_id !== null) {
                $existing->update(['video_url' => $data['video_url']]);
            }

            return $existing->id;
        }

        return Exercise::create([
            'user_id' => $owner?->id,
            'slug' => $slug,
            'name_en' => $data['name_en'] ?? Str::headline($slug),
            'name_ru' => $data['name_ru'] ?? null,
            'muscles' => $data['muscles'] ?? null,
            'video_url' => $data['video_url'] ?? null,
        ])->id;
    }
}
