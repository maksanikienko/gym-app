<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetLog extends Model
{
    protected $fillable = [
        'workout_session_id', 'day_exercise_id', 'exercise_id', 'set_no', 'is_warmup',
        'weight_kg', 'reps', 'seconds', 'rpe',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['is_warmup' => 'boolean', 'weight_kg' => 'float', 'rpe' => 'float'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /** Estimated 1RM: Epley, with reps-in-reserve added when RPE is known. */
    public static function e1rm(?float $weight, ?int $reps, ?float $rpe): ?float
    {
        if (! $weight || ! $reps) {
            return null;
        }
        $rir = $rpe !== null ? max(0, 10 - $rpe) : 0;

        return round($weight * (1 + ($reps + $rir) / 30), 1);
    }
}
