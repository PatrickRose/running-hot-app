<?php

namespace App\Policies;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\CouncilSession;
use App\Models\User;
use App\Services\CouncilService;

/**
 * Who may act at one sitting of the Council (rulebook 3.1).
 *
 * The Council is the CEOs' sub-game, so the seat is the authority: a CEO votes
 * for their own Corporation, and the Chair's powers belong to whoever holds the
 * Chair this turn rather than to a person. That is usually a Corporation, whose
 * CEO speaks for it. It can also be a seat Control has given somebody outright -
 * HM Government's - because the rotation is an order Council Control announces
 * (3.1.1) rather than a rule about who may be in it, and the game opens with the
 * Government in the Chair. Everything else about the Council is public - the
 * agenda is read out - so reading the page needs no ability at all.
 */
class CouncilSessionPolicy
{
    /**
     * Control can do anything at the Council, and needs to be able to: the
     * rulebook has Control picking the cards, signing off amendments and
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
     * Chair this sitting: keep two of what Control hands over, rule on custom
     * agendas, amend resolutions, declare a vote secret, and resolve it.
     *
     * Held by a Corporation or by a seat, rather than by a player, so the
     * question is whether this user holds the seat that is in the Chair. A
     * Corporation's is read from the CEO seat it fields; a character's is the
     * character itself. Either way a Chair nobody has claimed has nobody who
     * can chair, which is Control's cue to hand it elsewhere for the turn.
     */
    public function chair(User $user, CouncilSession $session): bool
    {
        if ($session->chair_id === null) {
            return false;
        }

        if ($session->chair_type === (new Character)->getMorphClass()) {
            return $this->holdsSeat($user, $session, $session->chair_id);
        }

        return $this->holdsCeoSeat($user, $session, $session->chair_id);
    }

    /**
     * Vote at this sitting.
     *
     * Every CEO may, the Chair included - and so may anybody Control has given
     * a seat of their own, which is how HM Government votes. That second case
     * is a ruling rather than a rule: 3.1 seats only the Corporations.
     *
     * The clock is asked about here rather than inside hasSeat(), because a
     * seat is a fact about the roster and voting is an act: the same CEO may
     * read back a finished sitting and may not cast a ballot in it.
     */
    public function vote(User $user, CouncilSession $session): bool
    {
        $game = $session->turn->game;

        return $game->isRunning()
            && app(CouncilService::class)->hasSeat($game, $user);
    }

    /**
     * Whether this user holds the character that is in the Chair.
     *
     * The seat has to still be one: a bloc Control has taken away is not a
     * Chair, whatever the sitting still has written on it.
     */
    private function holdsSeat(User $user, CouncilSession $session, int $characterId): bool
    {
        $game = $session->turn->game;

        if ($game->status !== GameStatus::Running) {
            return false;
        }

        return $game->characters()
            ->whereKey($characterId)
            ->where('user_id', $user->id)
            ->whereNotNull('council_votes')
            ->exists();
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
