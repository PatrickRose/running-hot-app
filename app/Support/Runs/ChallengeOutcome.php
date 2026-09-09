<?php

namespace App\Support\Runs;

use App\Models\RunDiceRoll;
use App\Models\RunEvent;

/**
 * What happened when the Runners threw themselves at a card.
 *
 * Carries both rolls rather than just the verdict, because the verdict on its
 * own is the thing players argue about. "You failed" invites an argument; "you
 * threw 6d8 and got 3,8,1,5,2,4 for 3 successes, Security threw 5d8 for 3, and
 * a tie goes to Security" ends one.
 */
readonly class ChallengeOutcome
{
    public function __construct(
        public ChallengeStrength $strength,
        public DicePool $pool,
        public RunDiceRoll $runnersRoll,
        public RunDiceRoll $securityRoll,
        /** True only on an outright win: a tie goes to Security (3.4.2). */
        public bool $runnersWon,
        public RunEvent $event,
    ) {}

    /**
     * The whole check in one sentence, as it goes into the log.
     */
    public function explain(): string
    {
        return sprintf(
            'Runners %s — %s against strength %s. Runners: %s. Security: %s.',
            $this->runnersWon ? 'broke through' : 'failed',
            $this->pool->total() === 1 ? '1 die' : $this->pool->total().' dice',
            $this->strength->explain(),
            $this->runnersRoll->readout(),
            $this->securityRoll->readout(),
        );
    }
}
