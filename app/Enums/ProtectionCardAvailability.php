<?php

namespace App\Enums;

/**
 * How a Protection Card can currently be obtained (rulebook 3.3.3).
 *
 * Control moves a card between these as the game progresses: a rumoured card
 * reaching the shop, or a research-only card being unlocked by a Corporation's
 * research player. Nothing in the application changes this on its own, because
 * the rulebook puts every one of those triggers in Control's hands.
 */
enum ProtectionCardAvailability: string
{
    case Available = 'available';
    case Rumoured = 'rumoured';
    case ResearchOnly = 'research_only';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'On sale now',
            self::Rumoured => 'Rumoured',
            self::ResearchOnly => 'Research only',
        };
    }
}
