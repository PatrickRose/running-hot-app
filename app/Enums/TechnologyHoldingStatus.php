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
 * Destroyed is the third outcome of a Run. The row is kept rather than deleted,
 * because "they may be able to salvage their research afterwards" is a
 * conversation with Control, and because the ledger of what a Corporation once
 * had is worth more than a tidy table.
 */
enum TechnologyHoldingStatus: string
{
    case Claimed = 'claimed';

    case Researched = 'researched';

    case Destroyed = 'destroyed';

    public function label(): string
    {
        return match ($this) {
            self::Claimed => 'Claimed',
            self::Researched => 'Researched',
            self::Destroyed => 'Destroyed',
        };
    }

    /**
     * Whether a card in this state occupies a slot in the Facility storing it.
     *
     * A claimed copy does: footnote 8 to 3.2.6 says so in as many words. A
     * destroyed one does not - the card is gone.
     */
    public function occupiesStorage(): bool
    {
        return $this !== self::Destroyed;
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
