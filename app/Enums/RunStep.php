<?php

namespace App\Enums;

/**
 * The four steps of the loop, in the order rulebook 3.4.2 gives them.
 *
 * Repeated until the Protection Cards run out or the Runners back out. Fixed by
 * the rules rather than by us, which is why this is an enum and not a column
 * Control can add to - a fifth step would be a different game.
 */
enum RunStep: string
{
    case Activate = 'activate';

    case Challenge = 'challenge';

    case Consequence = 'consequence';

    case Breather = 'breather';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The step that follows this one within a pass, or null at the end of one.
     *
     * The Breather has no next step here because what follows it is another
     * pass, another card, or the end of the run - a decision that needs the run
     * rather than just the step.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Activate => self::Challenge,
            self::Challenge => self::Consequence,
            self::Consequence => self::Breather,
            self::Breather => null,
        };
    }
}
