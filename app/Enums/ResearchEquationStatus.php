<?php

namespace App\Enums;

/**
 * Whether an equation has been paid out yet (rulebook 3.2.1).
 *
 * Two states rather than one because the rulebook asks for it: "Scoring can and
 * should be done while other players are taking their turns". So playing an
 * equation and scoring it are separate acts - the cards are spent, the hand and
 * the pool refill, the turn passes to the next Corporation, and the arithmetic
 * waits. A Pending equation is one somebody still owes themselves points for.
 *
 * Voided is Control's. An equation played by mistake, or one a ruling has
 * overturned, is marked rather than deleted: the cards it spent are already
 * gone, and a table that quietly loses a play is a table nobody can audit.
 */
enum ResearchEquationStatus: string
{
    case Pending = 'pending';

    case Scored = 'scored';

    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting to be scored',
            self::Scored => 'Scored',
            self::Voided => 'Voided',
        };
    }
}
