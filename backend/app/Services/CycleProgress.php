<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;

/**
 * Sequential progress through a cycle: days must be finished or skipped in order.
 */
class CycleProgress
{
    /** @var Collection<int, \App\Models\ProgramDay> */
    public Collection $days;

    /** @var Collection<int, WorkoutSession> keyed by program_day_id */
    public Collection $sessions;

    public function __construct(public Cycle $cycle, bool $withExercises = false)
    {
        $query = $cycle->program->orderedDays();
        if ($withExercises) {
            $query->with('exercises.exercise');
        }
        $this->days = $query->get();
        $this->sessions = $cycle->sessions()->get()->keyBy('program_day_id');
    }

    public function isClosed(int $dayId): bool
    {
        return in_array($this->sessions->get($dayId)?->status, WorkoutSession::CLOSED, true);
    }

    /** First day that is not finished/skipped — the only one that may be started. */
    public function nextDay()
    {
        return $this->days->first(fn ($d) => ! $this->isClosed($d->id));
    }

    public function status(int $dayId): string
    {
        $session = $this->sessions->get($dayId);
        if ($session) {
            return $session->status;
        }

        return $this->nextDay()?->id === $dayId ? 'next' : 'locked';
    }

    public function closedCount(): int
    {
        return $this->days->filter(fn ($d) => $this->isClosed($d->id))->count();
    }
}
