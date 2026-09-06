<?php

namespace App\Enums;

/**
 * A change the Chair wants made to a card's resolutions (rulebook 3.1.4).
 *
 * Held as an intention rather than applied, because every amendment needs
 * Council Control's sign-off. Until it is signed off the card still reads as it
 * stands, so an amendment can never change what a CEO is voting on without
 * Control having seen it.
 */
enum ResolutionAmendment: string
{
    case Addition = 'addition';
    case Removal = 'removal';
    case Rewording = 'rewording';

    public function label(): string
    {
        return match ($this) {
            self::Addition => 'Addition',
            self::Removal => 'Removal',
            self::Rewording => 'Rewording',
        };
    }
}
