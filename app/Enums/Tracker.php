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
 *
 * Research Points are four of these rather than a fifth thing: they move for the
 * same reasons the rest do, and they need the same ledger behind them - "why
 * does Gordon have eleven Cog?" is the question the audit trail exists for.
 */
enum Tracker: string
{
    case Income = 'income';
    case PoliticalWill = 'political_will';
    case CorporationCredits = 'corporation_credits';

    /**
     * Research Points, one tracker per suit (rulebook 3.2.1).
     *
     * They belong to the Corporation rather than to its Research player: 3.2.5
     * trades them "between different Corporations", and the amount a
     * Corporation holds is what is semi-secret. Four cases rather than one with
     * a suit, because a tracker is a column and these are four columns - and
     * because it puts all four on the Control panel beside Credits, which is
     * how Control overrides a score.
     */
    case ResearchCog = 'research_cog';
    case ResearchBrain = 'research_brain';
    case ResearchLeaf = 'research_leaf';
    case ResearchMaths = 'research_maths';

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
            self::ResearchCog => 'Cog Research Points',
            self::ResearchBrain => 'Brain Research Points',
            self::ResearchLeaf => 'Leaf Research Points',
            self::ResearchMaths => 'Maths Research Points',
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
            self::ResearchCog, self::ResearchBrain,
            self::ResearchLeaf, self::ResearchMaths => $this->researchSuit()->pointsColumn(),
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
            self::Income, self::PoliticalWill, self::CorporationCredits,
            self::ResearchCog, self::ResearchBrain,
            self::ResearchLeaf, self::ResearchMaths => Corporation::class,
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
            self::Wounds, self::Tags, self::Stability, self::CivilUnrest,
            self::ResearchCog, self::ResearchBrain,
            self::ResearchLeaf, self::ResearchMaths => 0,
            default => null,
        };
    }

    /**
     * The Research Point suit this tracker holds, or null for every tracker
     * that is not Research Points.
     */
    public function researchSuit(): ?ResearchSuit
    {
        return match ($this) {
            self::ResearchCog => ResearchSuit::Cog,
            self::ResearchBrain => ResearchSuit::Brain,
            self::ResearchLeaf => ResearchSuit::Leaf,
            self::ResearchMaths => ResearchSuit::Maths,
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
