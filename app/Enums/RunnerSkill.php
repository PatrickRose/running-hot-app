<?php

namespace App\Enums;

/**
 * The skills a Protection Card can challenge (rulebook 3.4.2).
 *
 * Naming follows App\Enums\Tracker: the rulebook uses "Brute" and "Brawn" for
 * the same skill, and this application standardises on Brawn.
 */
enum RunnerSkill: string
{
    case Brawn = 'brawn';
    case Hack = 'hack';

    public function label(): string
    {
        return match ($this) {
            self::Brawn => 'Brawn',
            self::Hack => 'Hack',
        };
    }

    /**
     * The column on the character holding this skill.
     */
    public function column(): string
    {
        return $this->value;
    }
}
