<?php

namespace App\Enums;

use App\Support\CardMarking;

/**
 * A marking printed on a research card that limits how it may be played
 * (rulebook 3.2.1, 3.2.3).
 *
 * Two of them, and they are not the same kind of rule - which is the whole
 * reason this is an enum of kinds rather than a single flag:
 *
 * - **No single** constrains the set holding it: the card cannot be the only
 *   card in its own set.
 * - **Restricted** constrains the *other* set: it names a suit, and the far
 *   side of the equation has to be that suit. "Other side must be Cog" is how
 *   the card prints it.
 *
 * The rulebook prints both markings and defines neither, so what they mean is
 * the designer's ruling rather than a reading. That is why they are written
 * down here rather than inferred from anything.
 *
 * A kind on its own is not a whole marking, because Restricted is meaningless
 * without the suit it names - {@see CardMarking} is the pair, and
 * it is what the rest of the application passes around. What lives here is only
 * what is true of the kind.
 *
 * An enum rather than the free text it started as, because App\Support\Equation
 * acts on it. A rule keyed off a string somebody typed is a rule that breaks on
 * a capital letter, and the equation rules are the one place in the research
 * game that must not be able to drift.
 */
enum ResearchCardMarking: string
{
    case NoSingle = 'no_single';
    case Restricted = 'restricted';

    /**
     * Whether the marking is incomplete without a suit.
     *
     * Restricted names the suit the other side has to be, so a Restricted
     * marking with no suit says nothing at all; No single names none, and one
     * carrying a suit would be a card printed with words the rules cannot read.
     */
    public function namesASuit(): bool
    {
        return match ($this) {
            self::NoSingle => false,
            self::Restricted => true,
        };
    }

    /**
     * The fewest cards the set holding this card may contain.
     */
    public function minimumSetSize(): int
    {
        return match ($this) {
            self::NoSingle => 2,
            self::Restricted => 1,
        };
    }

    /**
     * What the marking is called with no suit filled in, for a form offering
     * the choice.
     */
    public function label(): string
    {
        return match ($this) {
            self::NoSingle => 'No single',
            self::Restricted => 'Restricted',
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
