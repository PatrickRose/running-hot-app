<?php

namespace App\Enums;

/**
 * What a Protection Card does to the Runners who fail to break it (rulebook 3.4.2).
 *
 * The rulebook lists five, and says a consequence is "usually one (or more) of
 * the following" - so a card can carry several, and what any given card carries
 * is the sentence it prints rather than anything parsed. This enum is the set
 * of things the application knows how to *apply*; whoever is running the card
 * reads the words and says which of these they add up to.
 *
 * Two of the five are not damage at all. Retry sends the Runners back to the
 * Activate step on the same card, and End the Run finishes the run there and
 * then - and both are worse than a Wound, because a Wound is one bad die and a
 * Retry is the whole card again with more Alerts standing.
 */
enum RunConsequence: string
{
    /**
     * An Alert, which is both a temporary Credit for Security and a point of
     * strength on everything the Runners have left.
     */
    case Alert = 'alert';

    case Tag = 'tag';

    case Wound = 'wound';

    /** Back to Activate on the same card, after the Breather. */
    case Retry = 'retry';

    /** The run is unsuccessful, immediately. */
    case EndTheRun = 'end_the_run';

    public function label(): string
    {
        return match ($this) {
            self::Alert => 'Alert',
            self::Tag => 'Tag',
            self::Wound => 'Wound',
            self::Retry => 'Retry',
            self::EndTheRun => 'End the Run',
        };
    }

    /**
     * What Security pays in Alerts to add this consequence themselves.
     *
     * The second thing Alerts are for: as well as being temporary Credits,
     * Security "may also use any alerts to trigger one of the other effects".
     * Null for an Alert, which cannot buy itself.
     *
     * The prices are steep on purpose - 15 Alerts to end a run outright - and
     * they compete with the same Alerts being spent as Credits, which is the
     * decision the Security player is there to make.
     */
    public function alertCost(): ?int
    {
        return match ($this) {
            self::Alert => null,
            self::Tag => 2,
            self::Wound => 5,
            self::Retry => 12,
            self::EndTheRun => 15,
        };
    }

    /**
     * Whether this lands on one Runner rather than on the run.
     *
     * Wounds and Tags are taken "by a single player, decided by the Run
     * Leader"; Alerts go into the run's pool, and Retry and End the Run happen
     * to everybody at once.
     */
    public function appliesToCharacter(): bool
    {
        return $this === self::Tag || $this === self::Wound;
    }

    /**
     * The tracker this consequence moves, where it moves one.
     */
    public function tracker(): ?Tracker
    {
        return match ($this) {
            self::Tag => Tracker::Tags,
            self::Wound => Tracker::Wounds,
            default => null,
        };
    }
}
