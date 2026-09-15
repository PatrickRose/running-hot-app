<?php

namespace App\Enums;

/**
 * What a Runner spent an access on (rulebook 3.4.3).
 *
 * Four things, and the first two may each be taken once per run: there is one
 * Credits card in a Facility and one Facility effect, so the second Runner to
 * reach for either finds it gone. A technology access may be spent by every
 * Runner in the group, and a plot access as often as Control will wear.
 */
enum RunAccessKind: string
{
    /** The Facility's Credits card (3.4.3). */
    case Credits = 'credits';

    /** The Facility type's own effect - spy on a rival, take a blackmail file. */
    case FacilityEffect = 'facility_effect';

    /** A technology stored in the Facility, to copy, steal or destroy. */
    case Technology = 'technology';

    /** Something the Runner is chasing that only Control can answer. */
    case Plot = 'plot';

    public function label(): string
    {
        return match ($this) {
            self::Credits => 'Credits card',
            self::FacilityEffect => 'Facility effect',
            self::Technology => 'Technology',
            self::Plot => 'Plot access',
        };
    }

    /**
     * Whether a Facility only has one of these to give.
     *
     * The Credits card and the Facility's effect are each a single thing in the
     * building. The technologies are a pile, and a plot access is a question
     * asked of Control rather than an object taken off a shelf.
     */
    public function onlyOncePerRun(): bool
    {
        return $this === self::Credits || $this === self::FacilityEffect;
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
