<?php

namespace App\Support\Runs;

/**
 * What a run that did not get through is still worth (rulebook 3.4.4).
 *
 * A consolation rather than a reward: 1 Credit for every 3 Protection Cards the
 * Runners got past, rounding up, so 1-3 cards pays 1 and 4-6 pays 2. It goes to
 * "the last runner" - the one still standing when the run ended - which the
 * engine resolves; this is only the arithmetic.
 *
 * Control may also hand out plot information depending how far they got, which
 * is a conversation rather than a number and so is not modelled.
 */
class RunRewards
{
    /**
     * Credits for a run that failed.
     *
     * Rounding up rather than down matters at the bottom of the range: getting
     * past a single card still pays, so a group that dies on the second card
     * leaves with something.
     */
    public static function forFailedRun(int $cardsPassed): int
    {
        return (int) ceil(max(0, $cardsPassed) / 3);
    }
}
