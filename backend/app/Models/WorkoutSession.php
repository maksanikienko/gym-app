<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSession extends Model
{
    public const IN_PROGRESS = 'in_progress';
    public const FINISHED = 'finished';
    public const SKIPPED = 'skipped';

    /** Statuses that close a program day and unlock the next one. */
    public const CLOSED = [self::FINISHED, self::SKIPPED];

    protected $fillable = ['user_id', 'cycle_id', 'program_day_id', 'status', 'started_at', 'finished_at', 'note'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(ProgramDay::class, 'program_day_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(SetLog::class)->orderBy('id');
    }
}
