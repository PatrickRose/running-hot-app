<?php

namespace App\Enums;

/**
 * Where a technology card has got to (rulebook 3.2.2, 3.2.6).
 *
 * A card can be in a Corporation's hands without working for them. A copy a
 * Runner brought back, or one Research Control made for a partner, is Claimed:
 * it is stored in a Facility and it counts against that Facility's capacity
 * (3.2.6, footnote 8), but until the Corporation pays the discounted research
 * cost it is paper. Researched is the flipped card - paid for, and doing
 * whatever it says.
 *
 * Destroyed and Stolen are the two outcomes of a Run that take a card away from
 * the Corporation, and they are kept apart because they are not the same loss:
 * a destroyed technology leaves traces the Corporation may research again at a
 * discount, and a stolen one is intact in somebody else's hands. Neither row is
 * deleted - the ledger of what a Corporation once had is worth more than a tidy
 * table, and "they may be able to salvage their research afterwards" is a
 * conversation with Control that needs the row to still be there.
 */
enum TechnologyHoldingStatus: string
{
    case Claimed = 'claimed';

    case Researched = 'researched';

    case Destroyed = 'destroyed';

    case Stolen = 'stolen';

    public function label(): string
    {
        return match ($this) {
            self::Claimed => 'Claimed',
            self::Researched => 'Researched',
            self::Destroyed => 'Destroyed',
            self::Stolen => 'Stolen',
        };
    }

    /**
     * Whether a card in this state occupies a slot in the Facility storing it.
     *
     * A claimed copy does: footnote 8 to 3.2.6 says so in as many words. A
     * destroyed or stolen one does not - the card is not in the building any
     * more, whichever way it left.
     */
    public function occupiesStorage(): bool
    {
        return $this !== self::Destroyed && $this !== self::Stolen;
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
