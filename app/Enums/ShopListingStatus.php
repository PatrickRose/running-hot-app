<?php

namespace App\Enums;

/**
 * Where a line of the shop's list stands (rulebook 3.3.3).
 *
 * The rulebook hands Security players "a list of cards that will be available
 * to purchase at the start of the game", and splits that list in three: cards
 * available now, cards "rumoured to be in progress (and will become available
 * later in the game)", and cards that require specialised research and so
 * "will not be available for general sale". The first two are lines of this
 * shop and are these two cases; the third is not stocked at all, which
 * App\Services\ShopService refuses at the point a listing is written.
 *
 * Rumoured is a real line rather than an absent one, and that is the whole
 * reason this enum is not a boolean. A card nobody can buy yet is still
 * something Security is told about and plans around - "hold your Credits, the
 * Angel lands next turn" is a decision the list is there to let them make. A
 * shop that simply left it out would be a shop that told them nothing.
 *
 * Withdrawn is Control's, and is how a line leaves the list without taking its
 * sales record with it: a listing that has been bought from cannot be deleted,
 * because the purchases hanging off it are how Control answers "where did that
 * card come from?" three turns later.
 *
 * Nothing here moves on its own. A rumoured card reaching the shop is Control's
 * announcement to make (3.3.3 has Control announcing the cards available for
 * sale), so the application waits to be told.
 */
enum ShopListingStatus: string
{
    case OnSale = 'on_sale';
    case Rumoured = 'rumoured';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::OnSale => 'On sale',
            self::Rumoured => 'Rumoured',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Whether a line in this state may be bought from at all.
     *
     * Stock is a separate question and is asked separately: a sold-out line is
     * still on sale, and reads as such, which is what makes "first come first
     * served" legible to the player who arrived second.
     */
    public function isBuyable(): bool
    {
        return $this === self::OnSale;
    }

    /**
     * Whether the players whose counter this is get to see the line.
     *
     * Rumoured cards are the point of 3.3.3's list, so they show. A withdrawn
     * one does not: Control has taken it off the list, and a line saying
     * "you cannot have this and never will" is noise on a page whose job is to
     * be shopped from.
     */
    public function isOnTheList(): bool
    {
        return $this !== self::Withdrawn;
    }
}
