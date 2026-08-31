<?php

namespace App\Enums;

/**
 * How a card came to be in front of the Council this turn (rulebook 3.1.1 and
 * 3.1.3).
 *
 * Worth recording rather than inferring: all three end up as items to be voted
 * on, but only one of them came out of the deck, and the Chair may promote only
 * one previously submitted item per turn.
 */
enum AgendaItemSource: string
{
    /** One of the three Control drew for the Chair. */
    case Drawn = 'drawn';

    /** A custom agenda the Chair accepted as urgent. */
    case Urgent = 'urgent';

    /** Promoted out of the important pile at the start of Setup. */
    case Promoted = 'promoted';

    public function label(): string
    {
        return match ($this) {
            self::Drawn => 'Drawn from the deck',
            self::Urgent => 'Accepted as urgent',
            self::Promoted => 'Promoted from the important pile',
        };
    }
}
