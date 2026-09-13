<?php

namespace App\Enums;

/**
 * Where a research card is (rulebook 3.2.1).
 *
 * The research game is played out of two decks - a Corporation's private one
 * and the shared public one - so a card's zone plus whose it is says everything
 * about where it sits. A public card in Pool is one of the six face up on the
 * table; a private card in Hand is one of the five its Corporation is holding.
 *
 * Spent is where a card goes once it has been used in an equation. The rulebook
 * has nothing come back: you draw replacements, and play ends when your deck
 * runs dry. So a spent card is out for the rest of the phase rather than
 * shuffled back in, and it is kept rather than deleted so Control can see what
 * a Corporation has already played.
 */
enum ResearchZone: string
{
    /** Face down, waiting to be drawn. */
    case Deck = 'deck';

    /** One of the five a Corporation is holding. Never used for public cards. */
    case Hand = 'hand';

    /** One of the six public cards face up on the table. */
    case Pool = 'pool';

    /** Used in an equation, and out of play until the deck is gathered again. */
    case Spent = 'spent';

    public function label(): string
    {
        return match ($this) {
            self::Deck => 'Deck',
            self::Hand => 'Hand',
            self::Pool => 'Pool',
            self::Spent => 'Spent',
        };
    }

    /**
     * Whether a card in this zone can be played into an equation.
     */
    public function isPlayable(): bool
    {
        return $this === self::Hand || $this === self::Pool;
    }
}
