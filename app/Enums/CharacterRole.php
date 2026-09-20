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

    /**
     * Roles that walk into a Facility (rulebook 3.4).
     *
     * The same two roles again, and a third separate question for the reason
     * the one below is separate: 3.4 hands the Facility game to "Runners" as a
     * side rather than to one role, so a Freelancer submits, carries Equipment
     * and takes consequences like anybody else. This one is about what a player
     * is *offered* - the Runs and Equipment pages - where the two below are
     * about upkeep and about trackers.
     */
    public function goesOnRuns(): bool
    {
        return in_array($this, [self::Runner, self::Freelancer], true);
    }

    /**
     * Roles whose Credits, Wounds and Tags are their own.
     *
     * A Corporate player spends their Corporation's Credits rather than a purse
     * of their own, and the two roles that are organisations rather than people
     * - the Press outlets, and HM Government sitting on the Council as Other -
     * carry none of the three: they never walk into a Facility, so nothing in
     * the rulebook gives them a Wound or a Tag.
     *
     * The same two cases as healsDuringTeamTime(), and deliberately a separate
     * question: that one is about upkeep and this one is about what a player is
     * shown, so a role that gained one would not necessarily gain the other.
     */
    public function carriesOwnTrackers(): bool
    {
        return in_array($this, [self::Runner, self::Freelancer], true);
    }
}
