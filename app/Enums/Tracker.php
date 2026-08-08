<?php

namespace App\Enums;

use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Gang;

/**
 * Every numeric value Control moves during the game.
 *
 * Note on naming: the rulebook uses "Brute" (3.4.1) and "Brawn" (3.4.3, 3.5.1)
 * interchangeably for the same runner skill. This application standardises on
 * Brawn; renaming is a single change here and in the characters table.
 */
enum Tracker: string
{
    case Income = 'income';
    case PoliticalWill = 'political_will';
    case CorporationCredits = 'corporation_credits';

    case Notoriety = 'notoriety';

    case Wounds = 'wounds';
    case Tags = 'tags';
    case CharacterCredits = 'character_credits';

    case Stability = 'stability';
    case CivilUnrest = 'civil_unrest';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::PoliticalWill => 'Political Will',
            self::CorporationCredits, self::CharacterCredits => 'Credits',
            self::Notoriety => 'Notoriety',
            self::Wounds => 'Wounds',
            self::Tags => 'Tags',
            self::Stability => 'Stability',
            self::CivilUnrest => 'Civil Unrest',
        };
    }

    /**
     * The attribute on the subject model that this tracker reads and writes.
     */
    public function column(): string
    {
        return match ($this) {
            self::CorporationCredits, self::CharacterCredits => 'credits',
            default => $this->value,
        };
    }

    /**
     * The model class this tracker may be applied to.
     *
     * @return class-string
     */
    public function subjectClass(): string
    {
        return match ($this) {
            self::Income, self::PoliticalWill, self::CorporationCredits => Corporation::class,
            self::Notoriety => Gang::class,
            self::Wounds, self::Tags, self::CharacterCredits => Character::class,
            self::Stability, self::CivilUnrest => Game::class,
        };
    }

    /**
     * The lowest value this tracker may hold, or null if it is unbounded.
     *
     * Wounds and Tags are counts, and Stability bottoms out at zero because that
     * is the point at which the UK Government moves to close Procatorion
     * (rulebook 2.3.3). Credits and Political Will are deliberately unbounded so
     * Control can record a debt or a penalty without the model fighting them.
     */
    public function minimum(): ?int
    {
        return match ($this) {
            self::Wounds, self::Tags, self::Stability, self::CivilUnrest => 0,
            default => null,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function forSubject(object $subject): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $tracker): bool => $subject instanceof ($tracker->subjectClass()),
        ));
    }
}
