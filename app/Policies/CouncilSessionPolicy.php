<?php

namespace App\Policies;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\CouncilSession;
use App\Models\User;

/**
 * Who may act at one sitting of the Council (rulebook 3.1).
 *
 * The Council is the CEOs' sub-game, so the seat is the authority: a CEO votes
 * for their own Corporation, and the Chair's powers belong to whichever
 * Corporation holds the Chair this turn rather than to a person. Everything
 * else about the Council is public - the agenda is read out - so reading the
 * page needs no ability at all.
 */
class CouncilSessionPolicy
{
    /**
     * Control can do anything at the Council, and needs to be able to: the
     * rulebook has Control drawing the cards, signing off amendments and
     * judging attendance, and a ruling mid-game must not wait on the Chair
     * being at their laptop.
     *
     * Control of *this game*, though, which is why the session is asked about
     * rather than Control in general.
     */
    public function before(User $user, string $ability, ?CouncilSession $session = null): ?bool
    {
        $isControl = $session === null
            ? $user->isControl()
            : $user->isControlFor($session->turn->game);

        return $isControl ? true : null;
    }

    /**
     * Chair this sitting: keep two of the three drawn, rule on custom agendas,
     * amend resolutions, declare a vote secret, and resolve it.
     *
     * Held by a Corporation rather than by a player, and read from the CEO seat
     * that Corporation fields. A Corporation whose CEO seat is unclaimed has
     * nobody who can chair, which is Control's cue to hand the Chair elsewhere
     * for the turn.
     */
    public function chair(User $user, CouncilSession $session): bool
    {
        if ($session->chair_corporation_id === null) {
            return false;
        }

        return $this->holdsCeoSeat($user, $session, $session->chair_corporation_id);
    }

    /**
     * Vote at this sitting. Every CEO may, the Chair included.
     */
    public function vote(User $user, CouncilSession $session): bool
    {
        return $this->holdsCeoSeat($user, $session, null);
    }

    private function holdsCeoSeat(User $user, CouncilSession $session, ?int $corporationId): bool
    {
        $game = $session->turn->game;

        if ($game->status !== GameStatus::Running) {
            return false;
        }

        return $game->characters()
            ->where('user_id', $user->id)
            ->where('role', CharacterRole::Ceo)
            ->when($corporationId !== null, fn ($query) => $query->where('corporation_id', $corporationId))
            ->whereNotNull('corporation_id')
            ->exists();
    }
}
