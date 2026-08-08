<?php

namespace App\Enums;

/**
 * Player roles as listed in rulebook 1.3.
 */
enum CharacterRole: string
{
    case Ceo = 'ceo';
    case Security = 'security';
    case Research = 'research';
    case Runner = 'runner';
    case Freelancer = 'freelancer';
    case Press = 'press';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ceo => 'Corp CEO',
            self::Security => 'Corp Security',
            self::Research => 'Corp Research',
            self::Runner => 'Runner',
            self::Freelancer => 'Freelancer',
            self::Press => 'Press',
            self::Other => 'Other',
        };
    }

    /**
     * Roles that belong to a Corporation rather than a gang.
     */
    public function isCorporate(): bool
    {
        return in_array($this, [self::Ceo, self::Security, self::Research], true);
    }

    /**
     * Roles that heal a Wound for free during Team Time (rulebook 2.3.2).
     *
     * Freelancers take part in Runs and so carry Wounds and Tags too.
     */
    public function healsDuringTeamTime(): bool
    {
        return in_array($this, [self::Runner, self::Freelancer], true);
    }
}
