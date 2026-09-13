<?php

namespace App\Support;

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
 * Markings are the third thing the rules need, and the only one not printed as
 * a number. A card may carry more than one, and the two kinds pull in opposite
 * directions: No single is about the set holding the card, Restricted about the
 * set facing it.
 */
final readonly class EquationCard
{
    /**
     * @param  ResearchSuit|null  $suit  null for a wild card
     * @param  bool  $fromHand  false for one of the six public cards
     * @param  array<int, CardMarking>  $markings  what the card is printed with
     */
    public function __construct(
        public ?ResearchSuit $suit,
        public int $value,
        public bool $fromHand = false,
        public array $markings = [],
    ) {}

    public function isWild(): bool
    {
        return $this->suit === null;
    }

    /**
     * The fewest cards this card will tolerate in its own set.
     *
     * The strictest marking wins, so a card printed with two of them is held to
     * both rather than to whichever was read last.
     */
    public function minimumSetSize(): int
    {
        $minimum = 1;

        foreach ($this->markings as $marking) {
            $minimum = max($minimum, $marking->minimumSetSize());
        }

        return $minimum;
    }

    /**
     * The marking that forces this card's own set to be wider, if one does.
     */
    public function tooLonelyIn(int $setSize): ?CardMarking
    {
        foreach ($this->markings as $marking) {
            if ($setSize < $marking->minimumSetSize()) {
                return $marking;
            }
        }

        return null;
    }

    /**
     * Every suit this card demands of the far side of the equation.
     *
     * @return array<int, ResearchSuit>
     */
    public function demandsOfTheOtherSide(): array
    {
        $suits = [];

        foreach ($this->markings as $marking) {
            $suit = $marking->demandsOfTheOtherSide();

            if ($suit !== null) {
                $suits[$suit->value] = $suit;
            }
        }

        return array_values($suits);
    }
}
