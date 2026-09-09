<?php

namespace App\Enums;

/**
 * A marking on a research card that limits how it may be played
 * (rulebook 3.2.1, 3.2.3).
 *
 * One case, because the game has one. Two of the deck customisation rows on the
 * common tree sell a card "marked No single", and the rulebook never says
 * anywhere what that marking does - the answer is the designer's: a No single
 * card cannot be the only card in its set, so the side of the equation holding
 * it needs at least two cards.
 *
 * An enum rather than the free text it started as, because App\Support\Equation
 * now acts on it. A rule keyed off a string somebody typed is a rule that breaks
 * on a capital letter, and the equation rules are the one place in the research
 * game that must not be able to drift. The printed words live in label(), so the
 * card still reads as it is printed.
 */
enum ResearchCardRestriction: string
{
    case NoSingle = 'no_single';

    /**
     * The words printed on the card.
     */
    public function label(): string
    {
        return match ($this) {
            self::NoSingle => 'No single',
        };
    }

    /**
     * What the marking actually does, for a page that has room to say so.
     */
    public function description(): string
    {
        return match ($this) {
            self::NoSingle => 'Cannot be the only card on its side of an equation.',
        };
    }

    /**
     * The fewest cards the set holding this card may contain.
     */
    public function minimumSetSize(): int
    {
        return match ($this) {
            self::NoSingle => 2,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
