<?php

namespace App\Policies;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\PhaseType;
use App\Models\Character;
use App\Models\ShopListing;
use App\Models\User;

/**
 * Who may buy from the shop, and when (rulebook 3.3.3, 2.1).
 *
 * The division this application draws everywhere: the policy answers *who and
 * when*, and App\Services\ShopService answers *what the rules allow*. So the
 * seat, the claim and the clock are here, and the price, the stock and whether
 * a card can be held at all are there. A refusal that would still be a refusal
 * for Control belongs in the service, not in this file.
 *
 * The clock is a real part of the answer rather than tidiness. "The Corporation
 * shop will be open during the Setup Phase" (3.3.3), and 2.1 lists buying
 * equipment among the things Runners do in Setup - so a purchase during the
 * Action phase is a purchase out of time, and being out of time is exactly the
 * kind of thing Control waves through. That is what before() is for.
 */
class ShopListingPolicy
{
    /**
     * Control reaches every counter, at any point in the turn.
     *
     * A player will phone a purchase in, turn up at the desk between phases, or
     * be handed a card for a job well done, and none of that can wait for the
     * clock to come round again. Control of *this game*, though: a seat on one
     * game's Control team is not a seat on another's, and the listing says
     * which game is being asked about.
     */
    public function before(User $user, string $ability, ?ShopListing $listing = null): ?bool
    {
        $isControl = $listing === null
            ? $user->isControl()
            : $user->isControlFor($listing->game);

        return $isControl ? true : null;
    }

    /**
     * Buy this line, as this character.
     *
     * The character is named rather than inferred because a player may hold
     * more than one, and which of them is standing at the counter decides whose
     * Credits pay and whose hand the card lands in.
     *
     * The seat has to match the counter. 3.3.3 hands the Protection Card list
     * to the Security player, and 2.1 hands the market to the Runners - which
     * is the same boundary FacilityPolicy::defend draws and for the same
     * reason: a purse two people can spend out of is a purse neither can plan
     * with. The CEO and the Research player still read the list, because a
     * price list is not one of the things 3.4.2 keeps Secret; they simply do
     * not buy.
     */
    public function buy(User $user, ShopListing $listing, Character $buyer): bool
    {
        $game = $listing->game;

        if ($game->status !== GameStatus::Running) {
            return false;
        }

        if ($game->currentPhase()?->type !== PhaseType::Setup) {
            return false;
        }

        if ($buyer->user_id !== $user->id || $buyer->game_id !== $game->id) {
            return false;
        }

        if ($listing->isProtectionCard()) {
            return $buyer->role === CharacterRole::Security;
        }

        return in_array($buyer->role, [CharacterRole::Runner, CharacterRole::Freelancer], true);
    }
}
