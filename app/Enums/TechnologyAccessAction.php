<?php

namespace App\Enums;

use App\Support\Runs\AccessCheck;

/**
 * What a Runner does to a technology once the card is face up (rulebook 3.4.3).
 *
 * All three roll the same pool - the group's combined Brawn and Hack - and all
 * three read the successes off a printed band rather than against an opposing
 * roll. What differs is what the bands mean and what happens to the card.
 */
enum TechnologyAccessAction: string
{
    /** Make a copy; the Corporation keeps the card. */
    case Copy = 'copy';

    /** Take the card. Needs 8 successes, and the Corporation loses it. */
    case Steal = 'steal';

    /** Break it. Only the last band takes it away entirely. */
    case Destroy = 'destroy';

    public function label(): string
    {
        return match ($this) {
            self::Copy => 'Copy',
            self::Steal => 'Steal',
            self::Destroy => 'Destroy',
        };
    }

    /**
     * What the Runner is rolling against, for the screen.
     *
     * Copy and Destroy have no single target - they have bands, and rolling
     * more is simply better - so only a steal has a number to beat.
     */
    public function target(): ?int
    {
        return $this === self::Steal ? AccessCheck::STEAL_STRENGTH : null;
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
