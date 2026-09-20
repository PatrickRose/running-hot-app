<?php

namespace App\Policies;

use App\Models\AgendaCard;
use App\Models\Game;
use App\Models\User;
use App\Services\CouncilService;

/**
 * Who may write and hand on a custom agenda card (rulebook 3.1.3).
 *
 * A seat at the Council is what it takes. 3.1.3 hands blank cards to "players"
 * rather than to "CEOs", and this application read that literally at first -
 * any character in the game could write one. It is the designer's ruling that
 * it should not: somebody with no seat who wants an agenda raised has to
 * convince somebody who has one, which is the conversation the Council is for.
 * So the blank cards are the Council's, and Character::scopeOnTheCouncil is
 * the whole of who that is.
 *
 * What is restricted beyond that is somebody else's card: it stays theirs
 * through Control's remarks and back, because the card that reaches the Chair
 * has to be the one its author agreed to.
 */
class AgendaCardPolicy
{
    /**
     * Control of the game the card belongs to can do all of this, and needs to:
     * Control writes the deck and annotates a player's card, and the rulebook
     * has Control in the middle of 3.1.3 by design.
     *
     * The arguments are taken as they come rather than type-hinted, because
     * these abilities are not all asked about the same thing: `create` names
     * the game (there is no card yet), everything else names a card, and a
     * class-name ability hands the class through as a string.
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        $game = null;

        foreach ($arguments as $argument) {
            if ($argument instanceof AgendaCard) {
                $game = $argument->game;

                break;
            }

            if ($argument instanceof Game) {
                $game = $argument;

                break;
            }
        }

        $isControl = $game === null
            ? $user->isControl()
            : $user->isControlFor($game);

        return $isControl ? true : null;
    }

    /**
     * Write a card at all: anybody holding a seat at the Council in this
     * running game. Control comes through before().
     */
    public function create(User $user, Game $game): bool
    {
        return app(CouncilService::class)->hasSeat($game, $user);
    }

    /**
     * Change the words on a card, which only its author may do and only while
     * the card is in their hands.
     */
    public function update(User $user, AgendaCard $card): bool
    {
        return $this->isAuthor($user, $card) && $card->status->isEditableByAuthor();
    }

    /**
     * Hand the card on - to Control for remarks, or from there to the Chair.
     */
    public function submit(User $user, AgendaCard $card): bool
    {
        return $this->isAuthor($user, $card);
    }

    private function isAuthor(User $user, AgendaCard $card): bool
    {
        if (! $card->game->isRunning() || $card->submitted_by_character_id === null) {
            return false;
        }

        return $card->game->characters()
            ->where('id', $card->submitted_by_character_id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
