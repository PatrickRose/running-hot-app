<?php

namespace App\Enums;

/**
 * Where a run has got to.
 *
 * The rulebook only names two outcomes - a run is successful if the Runners
 * break through every Protection Card and unsuccessful otherwise (3.4.1) - so
 * there is deliberately no "abandoned" or "timed out" here. A group that backs
 * out at a Breather and a run the Action phase ended under both simply Failed,
 * which is what 3.4.5 says they are.
 */
enum RunStatus: string
{
    /**
     * The target has been named but the run has not begun.
     *
     * Targets are chosen in Secret at the start of the Action phase (3.4.1) and
     * every group at a Facility is ordered before any of them goes in, so a
     * submitted run is waiting for its turn in the queue.
     */
    case Submitted = 'submitted';

    case Running = 'running';

    case Succeeded = 'succeeded';

    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Whether the run is over, either way.
     */
    public function isFinished(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
