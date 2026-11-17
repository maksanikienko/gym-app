<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramWeek extends Model
{
    public $timestamps = false;

    protected $fillable = ['program_id', 'week_no', 'block'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ProgramDay::class)->orderBy('day_no');
    }
}
