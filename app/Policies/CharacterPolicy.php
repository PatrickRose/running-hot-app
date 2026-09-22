<?php

namespace App\Policies;

use App\Models\Character;
use App\Models\User;

/**
 * What a player may do with a seat they hold.
 *
 * One ability so far, and the division is `ShopListingPolicy`'s: this answers
 * *who*, and App\Services\EquipmentService answers *what the rules allow*. So
 * the seat and the claim are here, and whether there are copies to give at all
 * is there. A refusal that would still be a refusal for Control belongs in the
 * service rather than in this file.
 */
class CharacterPolicy
{
    /**
     * Control reaches every hand.
     *
     * Control of *this game*, though: a seat on one game's Control team is not
     * a seat on another's.
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
     * There is deliberately **no phase clock**, which is where this parts
     * company with `ShopListingPolicy`. The shop is a counter Control opens and
     * shuts, and 3.3.3 says so in as many words - but handing a card to
     * somebody is two players agreeing in a Discord channel, and the channels
     * are open all turn. 2.1 listing it under the Setup Phase describes when
     * the market runs rather than forbidding a Runner from passing a Shiv
     * across at any other moment, and a refusal there would only teach people
     * to phone Control instead. The one thing the run loop needs is that a card
     * already spent on a run is gone from the hand, and the service's own count
     * is what holds that.
     *
     * The *game's* clock is asked about, though, and that is the division "A
     * game off the clock" draws: a seat is a fact about the roster, and acting
     * is a question about the clock. Reading a hand is why `/equipment` opens
     * either side of the evening; handing a card over is an act, so it asks
     * `isRunning()` here where the act is, exactly as
     * `CouncilSessionPolicy::vote` does beside `hasSeat()`.
     */
    public function giveEquipment(User $user, Character $character): bool
    {
        if (! $character->game->isRunning()) {
            return false;
        }

        return $character->user_id === $user->id;
    }
}
