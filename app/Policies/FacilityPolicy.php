<?php

namespace App\Policies;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Facility;
use App\Models\User;

/**
 * Who may change a Facility's defences (rulebook 3.3.4).
 *
 * Installing and reordering Protection Cards used to be Control's alone, on the
 * reasoning that Security hands over a slip at the table. That has changed:
 * Security arranges their own stacks, and Control is left free for the rulings
 * only Control can make.
 *
 * What has not changed is who Security is. The seat is the authority - a
 * Security player defends their own Corporation's Facilities and nobody else's,
 * because a stack is Secret from every other Corporation (3.4.2) and arranging
 * a rival's defences would be worse than reading them.
 */
class FacilityPolicy
{
    /**
     * Control can already do this through its own routes, and needs to be able
     * to: a ruling mid-game must not wait on the Security player being at their
     * laptop. Every ability here is therefore Security's *in addition* to
     * Control's rather than instead of it.
     */
    public function before(User $user): ?bool
    {
        return $user->isControl() ? true : null;
    }

    /**
     * Arrange this Facility's Protection Cards.
     *
     * The CEO and the Research player hold Corporate seats and so already see
     * these stacks, which 3.4.2 keeps Secret from everyone else. Seeing them is
     * not arranging them: the rulebook gives Security the Facilities, and a
     * board three people can drag at once is a board nobody can trust.
     */
    public function defend(User $user, Facility $facility): bool
    {
        if ($facility->game->status !== GameStatus::Running) {
            return false;
        }

        return $facility->game->characters()
            ->where('user_id', $user->id)
            ->where('corporation_id', $facility->corporation_id)
            ->where('role', CharacterRole::Security)
            ->exists();
    }
}
