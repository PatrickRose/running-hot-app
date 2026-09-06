<?php

namespace App\Enums;

/**
 * Where an agenda card is (rulebook 3.1.1 and 3.1.3).
 *
 * A card's whole life is one of these, whether Control wrote it into the deck
 * or a player wrote it during Setup. The two kinds meet at WithChair: from
 * there a card Control handed over and a custom one are treated exactly alike,
 * which is what the rulebook does.
 */
enum AgendaCardStatus: string
{
    /** Control's, written into the game's deck and not yet picked. */
    case Deck = 'deck';

    /** A player is writing it. Theirs to edit until they hand it over. */
    case Draft = 'draft';

    /** Handed to Control, waiting for the remarks of 3.1.3. */
    case WithControl = 'with_control';

    /** Control has annotated it and given it back. The player submits it next. */
    case Annotated = 'annotated';

    /** With the Chair, awaiting urgent, important or rejected. */
    case WithChair = 'with_chair';

    /** In the Chair's hand: handed over by Control, not yet kept or discarded. */
    case InHand = 'in_hand';

    /** Up for vote this turn. */
    case Tabled = 'tabled';

    /** Accepted as important: eligible to be promoted in a future turn. */
    case Important = 'important';

    /** Returned to the player, who may take it to a future Chair. */
    case Rejected = 'rejected';

    /** Handed over, not kept, and out of play for this sitting. */
    case Discarded = 'discarded';

    /** Voted on, and the outcome is on the item. */
    case Voted = 'voted';

    public function label(): string
    {
        return match ($this) {
            self::Deck => 'In the deck',
            self::Draft => 'Draft',
            self::WithControl => 'With Control',
            self::Annotated => 'Annotated by Control',
            self::WithChair => 'With the Chair',
            self::InHand => 'In the Chair\'s hand',
            self::Tabled => 'Up for vote',
            self::Important => 'Held as important',
            self::Rejected => 'Rejected',
            self::Discarded => 'Discarded',
            self::Voted => 'Voted',
        };
    }

    /**
     * Whether this card is in front of the Council this turn, either waiting to
     * be voted on or already resolved. What the five-item cap of 3.1.3 counts.
     */
    public function isOnTheTable(): bool
    {
        return in_array($this, [self::Tabled, self::Voted], true);
    }

    /**
     * Whether the player who wrote this card may still edit it.
     *
     * Only while it is theirs. Once it is with Control or the Chair, changing
     * the words underneath them would be a different card from the one they are
     * reading.
     */
    public function isEditableByAuthor(): bool
    {
        return in_array($this, [self::Draft, self::Annotated, self::Rejected], true);
    }
}
