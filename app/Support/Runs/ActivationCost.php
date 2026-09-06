<?php

namespace App\Support\Runs;

use App\Enums\ProtectionKind;

/**
 * What it costs Security to turn a Protection Card on (rulebook 3.4.2).
 *
 * Physical cards are free. A cyber card costs the number of cyber cards already
 * Active, so the first is free, the second 1, the third 2 - which is why a deep
 * cyber stack is expensive to defend as well as expensive to build, and why a
 * Security player who is not Directing here can simply run out of budget
 * halfway down it and leave the rest of the stack Inactive.
 *
 * The two stacks are counted apart. A Facility with four Active physical cards
 * still activates its first cyber card free.
 */
class ActivationCost
{
    /**
     * @param  int  $activeCyberCards  cyber cards already Active in this Facility this phase
     */
    public static function for(ProtectionKind $kind, int $activeCyberCards): int
    {
        return $kind === ProtectionKind::Cyber
            ? max(0, $activeCyberCards)
            : 0;
    }

    /**
     * What turning on the whole of the rest of a cyber stack would cost.
     *
     * For the Security player deciding a budget at the start of the phase,
     * which 3.4.5 asks them to do quickly. The sum is triangular, so a stack of
     * five from cold is 0+1+2+3+4 = 10 Credits before a single Boost.
     */
    public static function forRemainingCyberStack(int $cardsToActivate, int $activeCyberCards = 0): int
    {
        $total = 0;

        for ($card = 0; $card < max(0, $cardsToActivate); $card++) {
            $total += $activeCyberCards + $card;
        }

        return $total;
    }
}
