<?php

namespace App\Support;

use App\Enums\ResearchCardRestriction;
use App\Enums\ResearchSuit;

/**
 * One card as an equation sees it (rulebook 3.2.1).
 *
 * A research card is a suit and a value, and that is all the equation rules
 * need - so this is what App\Support\Equation is built out of rather than the
 * Eloquent model. The rules can then be tested against the rulebook's own
 * worked examples without a database, which is the point: the arithmetic in
 * 3.2.1 is the fiddly part, not the storage.
 *
 * A wild card carries no suit. "Some cards are marked as wild and can be used
 * as any type", so its suit is not unknown, it is whichever the set needs.
 *
 * A restriction is the third thing the rules need, and the only one that is not
 * printed as a number: a No single card cannot be the only card in its set.
 */
final readonly class EquationCard
{
    /**
     * @param  ResearchSuit|null  $suit  null for a wild card
     * @param  bool  $fromHand  false for one of the six public cards
     * @param  ResearchCardRestriction|null  $restriction  the marking on the card, if it carries one
     */
    public function __construct(
        public ?ResearchSuit $suit,
        public int $value,
        public bool $fromHand = false,
        public ?ResearchCardRestriction $restriction = null,
    ) {}

    public function isWild(): bool
    {
        return $this->suit === null;
    }
}
