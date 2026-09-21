<?php

namespace App\Http\Controllers\Control;

use App\Enums\ShopListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\StockShopListingRequest;
use App\Models\Character;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\ShopListing;
use App\Models\ShopPurchase;
use App\Services\ShopService;
use App\Support\GamePresenter;
use App\Support\ShopPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shop as Control runs it (rulebook 3.3.3).
 *
 * The rulebook gives Control one job here and it is the whole job: "Control
 * will announce the cards available for sale". So this panel is the list - what
 * is on it, at what price, how many are left, and what has already gone - and
 * nothing on it is derived. A price is not read off the card sheet's cost
 * column, because the shop does not price cards the way that column suggests
 * and a wrong price baked in would be worse than none.
 *
 * Auctions are not here, and deliberately. "Control may also decide to auction
 * Protection Cards - in those cases the Security player who pays the most will
 * receive a copy" is an auction at the table, and what the application wants
 * afterwards is the result: Control moves the Credits with the tracker controls
 * and raises the holding on the card page, both of which already exist. An
 * auction modelled here would be a bidding UI nobody would use with the room in
 * front of them.
 */
class ShopController extends Controller
{
    public function __construct(private readonly ShopService $shop) {}

    public function index(Game $game, GamePresenter $presenter, ShopPresenter $shop): Response
    {
        return Inertia::render('control/games/shop', [
            'game' => $presenter->controlSummary($game),
            'shop' => $shop->forControl($game),
        ]);
    }

    /**
     * Put a card on the list, or change the line it is already on.
     */
    public function stock(Game $game, StockShopListingRequest $request): RedirectResponse
    {
        $card = $this->card($game, $request->string('family')->value(), $request->integer('card_id'));

        $listing = $this->shop->stock(
            $game,
            $card,
            $request->integer('price'),
            // `stock` absent or blank is the unlimited line rather than nought,
            // which is why this is not `$request->integer()`: that reads a null
            // as a zero, and would quietly sell out every unlimited line.
            $request->input('stock') === null || $request->input('stock') === ''
                ? null
                : $request->integer('stock'),
            ShopListingStatus::from($request->string('status')->value()),
            $request->input('notes'),
        );

        return back()->with('status', sprintf(
            '%s is %s at %d Credit(s), %s.',
            $card->getAttribute('name'),
            strtolower($listing->status->label()),
            $listing->price,
            $listing->stock === null
                ? 'with as many as anybody wants'
                : sprintf('%d left', $listing->stock),
        ));
    }

    /**
     * Take a line off the list. Refused once it has been bought from, which
     * ShopService says in as many words - withdrawing is what Control wants
     * there, and it keeps the sales.
     */
    public function destroy(Game $game, ShopListing $listing): RedirectResponse
    {
        abort_unless($listing->game_id === $game->id, 404);

        $name = $listing->stockable?->getAttribute('name') ?? 'That card';

        $this->shop->removeListing($listing);

        return back()->with('status', sprintf('%s is off the shop list.', $name));
    }

    /**
     * Buy on somebody's behalf.
     *
     * A player will phone one in, turn up at the desk between phases, or be
     * handed a card for a job that went well, and none of that should wait for
     * the clock. It is the same route the players use as far as the rules go:
     * the service still refuses a sold-out line, a purse that will not cover it
     * and a card the character could not hold.
     */
    public function buy(Game $game, ShopListing $listing, Request $request): RedirectResponse
    {
        abort_unless($listing->game_id === $game->id, 404);

        $validated = $request->validate([
            'character_id' => ['required', 'integer'],
        ]);

        /** @var Character $buyer */
        $buyer = $game->characters()->findOrFail($validated['character_id']);

        $purchase = $this->shop->buy($listing, $buyer, $request->user());

        return back()->with('status', sprintf(
            '%s bought %s for %d Credit(s).',
            $buyer->name,
            $listing->stockable?->getAttribute('name') ?? 'a card',
            $purchase->price_paid,
        ));
    }

    /**
     * Unwind a sale: the Credits go back, the copy comes back, the shelf
     * refills. The counterpart of unscoring an equation, and there for the same
     * reason - a shop run live will sell somebody the wrong card.
     */
    public function refund(Game $game, ShopPurchase $purchase, Request $request): RedirectResponse
    {
        abort_unless($purchase->game_id === $game->id, 404);

        $name = $purchase->listing?->stockable?->getAttribute('name') ?? 'the card';
        $buyer = $purchase->character->name ?? 'the buyer';

        $this->shop->refund($purchase, $request->user());

        return back()->with('status', sprintf('%s was refunded to %s.', $name, $buyer));
    }

    /**
     * The card a line is about to sell.
     *
     * The family decides which catalogue is looked in, so an id that names a
     * card in the other one is a 404 rather than a silently wrong listing.
     *
     * @return ProtectionCardType|EquipmentCardType
     */
    protected function card(Game $game, string $family, int $id): Model
    {
        return $family === 'protection'
            ? $game->protectionCardTypes()->findOrFail($id)
            : $game->equipmentCardTypes()->findOrFail($id);
    }
}
