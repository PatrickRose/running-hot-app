<?php

namespace App\Enums;

/**
 * How a Corporation came to hold a technology card (rulebook 3.2.5, 3.2.6).
 *
 * A technology on the tree is researched from scratch, but a card can also
 * arrive already printed: Research Control makes a copy when two Corporations
 * agree to share one (3.2.5), and a Run can come back with a copy or with the
 * card itself (3.2.6). Every one of those still has to be researched before it
 * works - what the card buys you is a discount on the cost.
 *
 * The discounts here are the ones the game runs with: a weak copy is worth 25%
 * off and a good copy or an outright theft 50%, with the resulting cost rounded
 * up. They are defaults rather than rules, because 3.2.6 leaves the discount on
 * a copy to "the strength of the copy" as Research Control judges it - so the
 * percentage is stored on the holding and Control may name another.
 */
enum TechnologyOrigin: string
{
    /** Bought off the tree at full price, with nothing to show Control. */
    case Researched = 'researched';

    /** A copy Research Control made because two Corporations agreed (3.2.5). */
    case Shared = 'shared';

    /** A Runner's rough copy: 25% off. */
    case WeakCopy = 'weak_copy';

    /** A Runner's clean copy: 50% off. */
    case GoodCopy = 'good_copy';

    /** The card itself, taken off a rival: 50% off. */
    case Stolen = 'stolen';

    public function label(): string
    {
        return match ($this) {
            self::Researched => 'Researched',
            self::Shared => 'Shared copy',
            self::WeakCopy => 'Weak copy',
            self::GoodCopy => 'Good copy',
            self::Stolen => 'Stolen',
        };
    }

    /**
     * The discount this kind of card is normally worth, as a percentage.
     *
     * A shared copy defaults to nothing off: 3.2.5 has Research Control make
     * the copy and says nothing about a discount, so Control sets one if the
     * deal they have just witnessed included it.
     */
    public function defaultDiscountPercent(): int
    {
        return match ($this) {
            self::Researched, self::Shared => 0,
            self::WeakCopy => 25,
            self::GoodCopy, self::Stolen => 50,
        };
    }

    /**
     * Whether a Corporation holding this needs every piece of a split
     * technology before it can use it (rulebook 3.2.7).
     *
     * The Corporation that owns a split technology works it with any one piece.
     * Anyone who took or copied it needs all of them - and a copy Research
     * Control made is a copy, so it is on the same footing as a stolen one.
     */
    public function needsEveryPiece(): bool
    {
        return $this !== self::Researched;
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
