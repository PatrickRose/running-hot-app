<?php

namespace App\Enums;

/**
 * Why a Runner is no longer on a run.
 *
 * The two are not interchangeable, which is the reason this exists rather than
 * a plain left_at. Walking away at a Breather "may have an effect on your
 * gang's Notoriety" (3.4.2); being incapacitated when Wounds reach Body takes
 * you out "with no notoriety effect" and hands any permanent Equipment you
 * played to the Security player you were running against. Recording only that
 * somebody left would lose the difference between abandoning your group and
 * being carried out.
 */
enum RunDeparture: string
{
    /** Chose to leave at the Breather step. */
    case Left = 'left';

    /** Wounds reached Body, so they left whether they liked it or not. */
    case Incapacitated = 'incapacitated';

    public function label(): string
    {
        return match ($this) {
            self::Left => 'Left the run',
            self::Incapacitated => 'Incapacitated',
        };
    }

    /**
     * Whether leaving this way can cost the gang Notoriety.
     *
     * True only for walking away, and even then it is Control's ruling rather
     * than a formula - the rulebook says "may have an effect", so the
     * application never moves Notoriety on its own here.
     */
    public function mayCostNotoriety(): bool
    {
        return $this === self::Left;
    }
}
