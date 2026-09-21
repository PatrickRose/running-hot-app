<?php

namespace App\Policies;

use App\Enums\GameStatus;
use App\Enums\PhaseType;
use App\Models\Character;
use App\Models\User;

/**
 * What a player may do with a seat they hold.
 *
 * One ability so far, and the division is `ShopListingPolicy`'s: this answers
 * *who and when*, and App\Services\EquipmentService answers *what the rules
 * allow*. So the seat, the claim and the clock are here, and whether there are
 * copies to give at all is there. A refusal that would still be a refusal for
 * Control belongs in the service rather than in this file.
 */
class CharacterPolicy
{
    /**
     * Control reaches every hand, at any point in the turn.
     *
     * A trade agreed in a Discord channel while the Action phase runs is
     * exactly the kind of thing Control waves through, and a player who cannot
     * get to their laptop still has cards to hand over. Control of *this game*,
     * though: a seat on one game's Control team is not a seat on another's.
     */
    public function before(User $user, string $ability, ?Character $character = null): ?bool
    {
        $isControl = $character === null
            ? $user->isControl()
            : $user->isControlFor($character->game);

        return $isControl ? true : null;
    }

    /**
     * Hand a card out of this seat's hand to somebody else (rulebook 2.1).
     *
     * The seat has to be yours, because giving spends what is in that hand. Who
     * may *receive* one is not asked here at all: 2.1 names no restriction on
     * the far side of the trade, and who may hold a card is Control's call
     * rather than a rule off the page.
     *
     * The clock is a real part of the answer rather than tidiness. 2.1 is the
     * Setup Phase, and buying equipment "from other players" is one of the
     * things it lists there - so a trade during the Action phase is a trade out
     * of time, and being out of time is what before() exists for.
     */
    public function giveEquipment(User $user, Character $character): bool
    {
        $game = $character->game;

        if ($game->status !== GameStatus::Running) {
            return false;
        }

        if ($game->currentPhase()?->type !== PhaseType::Setup) {
            return false;
        }

        return $character->user_id === $user->id;
    }
}
