<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramDay extends Model
{
    public $timestamps = false;

    protected $fillable = ['program_week_id', 'day_no', 'title_en', 'title_ru'];

    public function week(): BelongsTo
    {
        return $this->belongsTo(ProgramWeek::class, 'program_week_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(DayExercise::class)->orderBy('position');
    }
}
