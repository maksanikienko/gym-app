<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayExercise extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'program_day_id', 'exercise_id', 'position', 'sets', 'warmup_sets', 'reps', 'reps_max', 'reps_text', 'seconds',
        'target_rpe', 'load_pct', 'rest_min', 'rest_max', 'notes_en', 'notes_ru',
    ];

    protected function casts(): array
    {
        return ['target_rpe' => 'float', 'load_pct' => 'float'];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(ProgramDay::class, 'program_day_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
