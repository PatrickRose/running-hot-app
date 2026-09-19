<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Game;
use App\Models\ShopListing;
use App\Services\ShopService;
use App\Support\GamePresenter;
use App\Support\ShopPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shop as the players use it (rulebook 3.3.3, and 2.1 for the market).
 *
 * Two counters on one page, because plenty of people are at both: a user claims
 * characters rather than a side, and somebody running a Freelancer on Saturday
 * may be sitting in a Security chair on Sunday. Which counters they are shown
 * is ShopPresenter's answer.
 *
 * Thin, like every player-facing route in this application. The whole of the
 * exchange is App\Services\ShopService's - the stock, the purse, the copy and
 * the record - so this route cannot grow its own copy of a rule that Control's
 * route would then be missing.
 */
class ShopController extends Controller
{
    public function __construct(private readonly ShopService $shop) {}

    public function index(Request $request, GamePresenter $game, ShopPresenter $shop): Response
    {
        $current = Game::current();

        return Inertia::render('shop', [
            'game' => $current === null ? null : $game->summary($current),
            'shop' => $current === null ? null : $shop->forPlayer($current, $request->user()),
        ]);
    }

    /**
     * Buy one copy.
     *
     * The character is named rather than inferred from the user, because a
     * player may hold more than one and which of them is standing at the
     * counter decides whose Credits pay and whose hand the card lands in.
     */
    public function buy(ShopListing $listing, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'character_id' => ['required', 'integer'],
        ]);

        /** @var Character $buyer */
        $buyer = $listing->game->characters()->findOrFail($validated['character_id']);

        Gate::authorize('buy', [$listing, $buyer]);

        $purchase = $this->shop->buy($listing, $buyer, $request->user());

        return back()->with('status', sprintf(
            '%s bought %s for %d Credit(s).',
            $buyer->name,
            $listing->stockable?->getAttribute('name') ?? 'a card',
            $purchase->price_paid,
        ));
    }
}
