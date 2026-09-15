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

    /**
     * The Credits card in a Facility (rulebook 3.4.3).
     *
     * Two sums added together, both printed as tables and both continued here
     * because the book says "and so on" to the second and gives a rule for the
     * first. Nothing about this is Control's to set: it is read off what the
     * Facility is actually holding at the moment the run gets in.
     *
     * @param  int  $protectionCards  installed, *not* activated - a card
     *                                Security could not afford to switch on is
     *                                still a card in the building
     * @param  int  $technologies  stored in the Facility, the Credits card
     *                             itself not included; this adds it
     */
    public static function forCreditsCard(int $protectionCards, int $technologies): int
    {
        return self::fromProtectionCards($protectionCards)
            + self::fromTechnologies($technologies);
    }

    /**
     * Credits for the Protection Cards standing in the Facility.
     *
     * "5 cards or less: the number of cards", then 7, 8, 10, 12, 15 at six
     * through ten, and "an additional 3 Credits for each card over 10". The
     * middle of that is a printed list rather than a curve - the steps are
     * 1, 2, 2, 3, 3 - so it is written out, and only the tail is arithmetic.
     */
    public static function fromProtectionCards(int $cards): int
    {
        $cards = max(0, $cards);

        $printed = [6 => 7, 7 => 8, 8 => 10, 9 => 12, 10 => 15];

        if ($cards <= 5) {
            return $cards;
        }

        return $printed[$cards] ?? 15 + (($cards - 10) * 3);
    }

    /**
     * Credits for what the Facility is storing.
     *
     * 0, 1, 3, 6, 10 for one through five technologies and then "and so on" -
     * the triangular numbers, so n technologies pay (n-1)n/2. Counted
     * "including the Credits card", so the Credits card is added here rather
     * than at every call site.
     */
    public static function fromTechnologies(int $technologies): int
    {
        $counted = max(0, $technologies) + 1;

        return (int) (($counted - 1) * $counted / 2);
    }
}
