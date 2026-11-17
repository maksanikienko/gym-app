<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Exercise extends Model
{
    protected $fillable = ['user_id', 'slug', 'name_en', 'name_ru', 'muscles', 'equipment', 'video_url'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['muscles' => 'array'];
    }

    /** Shared catalogue + the given user's own exercises. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    public function substitutes(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'exercise_substitutions', 'exercise_id', 'substitute_id')
            ->withPivot(['note_en', 'note_ru']);
    }
}
