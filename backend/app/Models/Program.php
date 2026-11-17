<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Program extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'name_en', 'name_ru', 'description_en', 'description_ru',
        'days_per_week', 'visibility', 'source',
    ];

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->where(fn ($w) => $w->where('visibility', 'public')->orWhere('user_id', $user->id));
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->visibility === 'public' || $this->user_id === $user->id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(ProgramWeek::class)->orderBy('week_no');
    }

    public function days(): HasManyThrough
    {
        return $this->hasManyThrough(ProgramDay::class, ProgramWeek::class);
    }

    /** All days in training order (week, then day). */
    public function orderedDays()
    {
        return ProgramDay::query()
            ->join('program_weeks', 'program_weeks.id', '=', 'program_days.program_week_id')
            ->where('program_weeks.program_id', $this->id)
            ->orderBy('program_weeks.week_no')
            ->orderBy('program_days.day_no')
            ->select('program_days.*', 'program_weeks.week_no');
    }
}
