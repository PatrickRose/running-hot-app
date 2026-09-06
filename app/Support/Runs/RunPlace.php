<?php

namespace App\Support\Runs;

/**
 * Where one group ended up in the queue, and what put it there.
 *
 * The reason travels with the position because the ordering cannot be
 * recomputed once it has happened: the seventh tiebreaker is a d8, so a group
 * asking "why did they go first?" a turn later has only what was written down
 * at the time.
 */
readonly class RunPlace
{
    public function __construct(
        public int $runId,
        /** 1 goes first. */
        public int $position,
        /** Which of {@see RunOrdering::RULES} settled it, or null for a lone group. */
        public ?int $rule,
        public string $reason,
        /** What this group's Run Leader rolled, whether or not it mattered. */
        public int $roll,
    ) {}

    /**
     * The reason as it goes into the run's order_reason column.
     *
     * The roll is named only when it was the thing that decided the order:
     * every group rolls, and reporting a die that changed nothing would invite
     * an argument about a number that never counted.
     */
    public function explain(): string
    {
        if ($this->rule === 7) {
            return sprintf('%s (%d)', $this->reason, $this->roll);
        }

        return $this->reason;
    }
}
