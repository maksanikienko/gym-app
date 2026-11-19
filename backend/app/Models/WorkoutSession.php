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

    /** A workout with no new sets for this long is considered abandoned and closed automatically. */
    public const AUTO_CLOSE_AFTER_HOURS = 3;

    protected $fillable = ['user_id', 'cycle_id', 'program_day_id', 'status', 'started_at', 'finished_at', 'note', 'skipped_slots'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'skipped_slots' => 'array'];
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

    /**
     * Close the user's workouts left open (app closed, forgot to finish): no activity for
     * AUTO_CLOSE_AFTER_HOURS → finished at the last logged set, or annulled if nothing was logged.
     * Called lazily from the endpoints that show workouts, so no scheduler is needed.
     */
    public static function closeStale(int $userId): void
    {
        $cutoff = now()->subHours(self::AUTO_CLOSE_AFTER_HOURS);

        static::where('user_id', $userId)
            ->where('status', self::IN_PROGRESS)
            ->where('started_at', '<', $cutoff)
            ->with('sets')
            ->get()
            ->each(function (self $session) use ($cutoff) {
                $lastActivity = $session->sets->max('updated_at');
                if ($lastActivity && $lastActivity->gte($cutoff)) {
                    return; // still training
                }
                $worked = $session->sets->contains(fn (SetLog $s) => ! $s->is_warmup);
                $session->update([
                    'status' => $worked ? self::FINISHED : self::SKIPPED,
                    'finished_at' => $lastActivity ?? $session->started_at,
                ]);
            });
    }
}
