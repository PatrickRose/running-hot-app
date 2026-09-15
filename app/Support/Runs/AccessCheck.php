<?php

namespace App\Support\Runs;

/**
 * What a Runner's dice buy them once they are inside (rulebook 3.4.3).
 *
 * The three things you can do to a technology are all the same shape - the
 * group rolls its skill and counts successes - and all three read those
 * successes off a printed band rather than against an opposing roll. There is
 * no consequence for failing one of these, which is what makes them unlike a
 * Protection Card: a failed copy leaves the card on the list to try again.
 *
 * The dice come from {@see DicePool}: the Run Leader's full skill and a share
 * of everyone else's, exactly as at a card. What differs is the skill itself -
 * 3.4.3 rolls "the combination of your Brawn and Hack" rather than one of them,
 * so there is nothing here to choose between.
 */
class AccessCheck
{
    /**
     * The Steal score a technology is defended by.
     *
     * Flat rather than per-technology: the card sheet carries copy and destroy
     * strengths and no Steal column at all, and the game runs on 8 for every
     * technology. A per-card score would be a column nothing fills in.
     */
    public const STEAL_STRENGTH = 8;

    /**
     * Successes needed before a copy is worth anything at all.
     */
    public const COPY_WEAK = 1;

    /**
     * Successes for a copy good enough to halve the research cost.
     */
    public const COPY_GOOD = 4;

    /**
     * How good a copy this many successes made, as a research discount.
     *
     * "If you roll no successes, your copy attempt fails. If you roll some
     * successes, you create a weak copy that gives a 25% discount. If you roll
     * four successes, you create a good copy that gives a 50% discount." Null
     * is a failed attempt rather than a copy worth nothing, because the two are
     * different things: a failure leaves the card to be tried again.
     *
     * The percentages match TechnologyOrigin's own defaults, which is where a
     * copy's worth is finally decided - this only says which kind was made.
     */
    public static function copyDiscount(int $successes): ?int
    {
        if ($successes >= self::COPY_GOOD) {
            return 50;
        }

        if ($successes >= self::COPY_WEAK) {
            return 25;
        }

        return null;
    }

    /**
     * How much of a technology this many successes destroyed.
     *
     * Four printed bands, and every one of them short of the last leaves the
     * Corporation able to research it again at some discount - so what a
     * destroy really produces is a discount for its *owner*, which is Control's
     * to apply. What is recorded is which band was reached.
     *
     * The 8 and 12 bands need destroy equipment or skills, which is not
     * modelled (nobody's Equipment is), so they are reachable on the dice and
     * the event says the band was claimed. Control settles whether the Runner
     * had the gear.
     */
    public static function destroyBand(int $successes): ?int
    {
        foreach ([12, 8, 4, 2] as $band) {
            if ($successes >= $band) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Whether a destroy at this band takes the technology off the board.
     *
     * Only the last one does: "you overly succeed and destroy the technology
     * and all useful traces". Everything below it leaves traces, which is a
     * discount rather than a removal.
     */
    public static function destroyIsTotal(?int $band): bool
    {
        return $band === 12;
    }

    /**
     * Whether a steal attempt beat the technology's Steal score.
     */
    public static function stealSucceeds(int $successes, int $strength = self::STEAL_STRENGTH): bool
    {
        return $successes >= $strength;
    }
}
