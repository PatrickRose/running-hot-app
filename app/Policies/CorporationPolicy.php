<?php

namespace App\Policies;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Corporation;
use App\Models\User;

/**
 * Who may play a Corporation's research game (rulebook 3.2).
 *
 * The Research seat, and nobody else. That is narrower than the Facility board,
 * where every Corporate seat at least reads the stacks: this is not a matter of
 * secrecy but of who is holding the cards. A hand two people can play from is a
 * hand neither can plan with, and the same goes for a pile of Research Points
 * that the CEO can spend out from under the Research player.
 *
 * The CEO and Security still see the Corporation's points and its technologies
 * on their own pages - what a Corporation has researched is not secret from the
 * Corporation - they simply cannot act.
 */
class CorporationPolicy
{
    /**
     * Control can do all of this through its own routes and needs to be able
     * to: Research Control prices custom proposals, settles trades and scores
     * equations for players who are away from a screen, and none of that can
     * wait. Control of *this game*, though - a seat on one game's Control team
     * is not a seat on another's.
     */
    public function before(User $user, string $ability, ?Corporation $corporation = null): ?bool
    {
        $isControl = $corporation === null
            ? $user->isControl()
            : $user->isControlFor($corporation->game);

        return $isControl ? true : null;
    }

    /**
     * Play this Corporation's research game: make equations, take the points,
     * spend them on the tree and trade them away.
     */
    public function research(User $user, Corporation $corporation): bool
    {
        if ($corporation->game->status !== GameStatus::Running) {
            return false;
        }

        return $corporation->game->characters()
            ->where('user_id', $user->id)
            ->where('corporation_id', $corporation->id)
            ->where('role', CharacterRole::Research)
            ->exists();
    }
}
