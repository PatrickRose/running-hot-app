<?php

namespace App\Enums;

/**
 * Which side of a run a roll belongs to.
 *
 * Two sides rather than a character per roll, because the Runners' challenge
 * pool is a single roll assembled from the whole group: the Run Leader's full
 * skill plus half of everybody else's, or a quarter if they are Wounded
 * (3.4.2). Attributing it to one person would misrepresent whose it was.
 */
enum DiceRoller: string
{
    case Runners = 'runners';

    case Security = 'security';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
