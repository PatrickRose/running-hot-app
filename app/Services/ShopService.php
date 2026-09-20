<?php

namespace App\Services;

use App\Enums\CharacterRole;
use App\Enums\ShopListingStatus;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\ShopListing;
use App\Models\ShopPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The shop: what Control has put out, at what price, and what happens when
 * somebody buys one (rulebook 3.3.3, and 2.1 for the Runners' market).
 *
 * The rulebook gives the shop two sentences and one allocation rule: Control
 * announces the cards available for sale, and they are bought first come first
 * served. Everything else about a shop - haggling, credit, a limit per
 * Corporation, who gets told first - is a conversation at the table, so none of
 * it is here. What is here is the list, the price, the count, and the exchange.
 *
 * **Any card can be put out, including a research-only one.** 3.3.3 says those
 * "will not be available for general sale", and that was enforced here until it
 * got in the way of the thing it was protecting: the shop is how Control hands
 * a card over at a price, and a card the tree was supposed to unlock is exactly
 * the sort of thing Control sells once during a game because the table has got
 * somewhere interesting. Control always wins, and a rule the organisers have to
 * go and edit a catalogue to get round is a rule fighting them. The card's own
 * availability still travels to the panel, so a line Control may not have meant
 * to put out says what it is.
 *
 * Two counters, and they are genuinely different transactions rather than one
 * with a parameter:
 *
 * - A **Protection Card** is bought by a Security player, paid for out of the
 *   *Corporation's* Credits, and lands in the Corporation's hand. 3.3.3 puts
 *   the list in Security's hands and 3.3.4 makes the copies the Corporation's.
 * - An **Equipment card** is bought by a Runner or Freelancer, paid for out of
 *   *their own* Credits, and lands in their own hand. 3.4.1 caps and takes away
 *   equipment per person, so the purse is per person too.
 *
 * Which of the two applies is read off the listing and nowhere else, so a
 * caller never has to say.
 *
 * Nothing in this class writes a holding itself. A copy reaches a Corporation
 * through App\Services\FacilityDefenceService and a Runner through
 * App\Services\EquipmentService, because those are the one writer of their
 * table apiece and a shop that grew its own copy of the write would be a second
 * place a count could move. Credits move through App\Services\TrackerService
 * like every other number the game argues about, so a purchase is a row in the
 * ledger with the buyer's name and the card's on it.
 *
 * Who may buy, and when, is not asked here at all: that is
 * App\Policies\ShopListingPolicy's, which is also where Control's override lives. What
 * this class refuses, it refuses to Control as well - a Corporate seat has no
 * purse to buy out of whoever presses the button.
 */
class ShopService
{
    public function __construct(
        private readonly TrackerService $trackers,
        private readonly FacilityDefenceService $defence,
        private readonly EquipmentService $equipment,
    ) {}

    /**
     * Put a card on the list, or change the line it is already on.
     *
     * One line per card, so this is an upsert rather than an insert: Control
     * changing their mind about a price is an edit, because two prices for the
     * same card is a question nobody can answer at the counter.
     *
     * @param  ProtectionCardType|EquipmentCardType  $card
     */
    public function stock(
        Game $game,
        Model $card,
        int $price,
        ?int $stock = null,
        ShopListingStatus $status = ShopListingStatus::OnSale,
        ?string $notes = null,
    ): ShopListing {
        $this->guardCardBelongsToGame($game, $card);

        if ($price < 0) {
            throw ValidationException::withMessages([
                'price' => 'A card cannot cost fewer than no Credits.',
            ]);
        }

        if ($stock !== null && $stock < 0) {
            throw ValidationException::withMessages([
                'stock' => 'The shop cannot hold fewer than no copies of a card.',
            ]);
        }

        /** @var ShopListing $listing */
        $listing = $game->shopListings()->updateOrCreate(
            [
                'stockable_type' => $card->getMorphClass(),
                'stockable_id' => $card->getKey(),
            ],
            [
                'price' => $price,
                'stock' => $stock,
                'status' => $status,
                'notes' => $notes,
            ],
        );

        return $listing;
    }

    /**
     * Take a line off the list entirely.
     *
     * Refused once anybody has bought from it, because the purchases hanging
     * off a line are how Control answers "where did that card come from?" three
     * turns later, and they would go with it. Withdrawing is what Control wants
     * there: the line leaves the players' list and its history stays.
     */
    public function removeListing(ShopListing $listing): void
    {
        $sold = $listing->purchases()->count();

        if ($sold > 0) {
            throw ValidationException::withMessages([
                'listing' => sprintf(
                    'This line has been bought from %d time(s), so deleting it would '
                    .'take that record with it. Withdraw it instead.',
                    $sold,
                ),
            ]);
        }

        $listing->delete();
    }

    /**
     * Sell one copy.
     *
     * The listing is locked for the length of the sale, which is the whole of
     * what "first come first served" (3.3.3) needs from the application: two
     * Security players reaching for the last Angel at the same moment is
     * exactly the case the rulebook is answering, and without the lock they
     * would both get it.
     *
     * Everything lands or nothing does. The Credits, the copy and the stock are
     * three writes that must agree, and a purchase that took the money without
     * handing the card over is the worst way for this to fail.
     */
    public function buy(ShopListing $listing, Character $buyer, ?User $actor = null): ShopPurchase
    {
        return DB::transaction(function () use ($listing, $buyer, $actor): ShopPurchase {
            /** @var ShopListing $listing */
            $listing = ShopListing::query()
                ->lockForUpdate()
                ->with('stockable')
                ->findOrFail($listing->id);

            if ($buyer->game_id !== $listing->game_id) {
                throw ValidationException::withMessages([
                    'character_id' => 'That character is not in this game.',
                ]);
            }

            $card = $listing->stockable;

            if ($card === null) {
                throw ValidationException::withMessages([
                    'listing' => 'The card this line sells is no longer in the catalogue.',
                ]);
            }

            if (! $listing->status->isBuyable()) {
                throw ValidationException::withMessages([
                    'listing' => sprintf(
                        '%s is %s, and is not for sale.',
                        $card->getAttribute('name'),
                        strtolower($listing->status->label()),
                    ),
                ]);
            }

            if ($listing->isSoldOut()) {
                throw ValidationException::withMessages([
                    'listing' => sprintf('The shop has sold out of %s.', $card->getAttribute('name')),
                ]);
            }

            $corporation = $this->deliver($listing, $card, $buyer, $actor);

            if ($listing->stock !== null) {
                $listing->decrement('stock');
            }

            /** @var ShopPurchase $purchase */
            $purchase = ShopPurchase::create([
                'game_id' => $listing->game_id,
                'shop_listing_id' => $listing->id,
                'phase_id' => $listing->game->currentPhase()?->id,
                'character_id' => $buyer->id,
                'corporation_id' => $corporation?->id,
                'price_paid' => $listing->price,
            ]);

            return $purchase;
        });
    }

    /**
     * Undo a sale: the Credits go back, the copy comes back, the shelf refills.
     *
     * Control's, and the counterpart of unscoring an equation. A shop run live
     * will sell somebody the wrong card, and the remedy has to move all three
     * of those together or the next person to look at the stock will not
     * believe it.
     *
     * Taking the copy back can fail, and should: a Protection Card already
     * standing in a Facility is not in the Corporation's hand to give back, and
     * Control removes it from the stack first. That refusal is better than a
     * refund that quietly leaves the Corporation a card up.
     */
    public function refund(ShopPurchase $purchase, ?User $actor = null): void
    {
        DB::transaction(function () use ($purchase, $actor): void {
            /** @var ShopListing $listing */
            $listing = ShopListing::query()->lockForUpdate()->findOrFail($purchase->shop_listing_id);

            $card = $listing->stockable;
            $buyer = $purchase->character;

            if ($card === null || $buyer === null) {
                throw ValidationException::withMessages([
                    'purchase' => 'Neither the card nor the buyer is still on record, so this cannot be unwound.',
                ]);
            }

            $name = $card->getAttribute('name');

            if ($listing->isProtectionCard()) {
                /** @var Corporation|null $corporation */
                $corporation = $purchase->corporation;

                if ($corporation === null) {
                    throw ValidationException::withMessages([
                        'purchase' => 'The Corporation that paid for this is no longer on record.',
                    ]);
                }

                /** @var ProtectionCardType $card */
                $this->defence->takeCopyFromHand($corporation, $card, sprintf(
                    '%s has no copy of %s in hand to give back.',
                    $corporation->name,
                    $name,
                ));

                $this->trackers->adjust(
                    $corporation,
                    Tracker::CorporationCredits,
                    $purchase->price_paid,
                    sprintf('Refunded %s from the shop', $name),
                    $actor,
                );
            } else {
                /** @var EquipmentCardType $card */
                if ($buyer->equipmentCopiesOf($card->id) < 1) {
                    throw ValidationException::withMessages([
                        'purchase' => sprintf(
                            '%s has no copy of %s left to give back.',
                            $buyer->name,
                            $name,
                        ),
                    ]);
                }

                $this->equipment->takeCopy($buyer, $card);

                $this->trackers->adjust(
                    $buyer,
                    Tracker::CharacterCredits,
                    $purchase->price_paid,
                    sprintf('Refunded %s from the shop', $name),
                    $actor,
                );
            }

            if ($listing->stock !== null) {
                $listing->increment('stock');
            }

            $purchase->delete();
        });
    }

    /**
     * Hand the card over and take the money, whichever counter this is.
     *
     * @param  ProtectionCardType|EquipmentCardType  $card
     * @return Corporation|null The Corporation that paid, where one did
     */
    protected function deliver(ShopListing $listing, Model $card, Character $buyer, ?User $actor): ?Corporation
    {
        if ($listing->isProtectionCard()) {
            /** @var ProtectionCardType $card */
            return $this->sellProtectionCard($listing, $card, $buyer, $actor);
        }

        if ($listing->isEquipment()) {
            /** @var EquipmentCardType $card */
            $this->sellEquipment($listing, $card, $buyer, $actor);

            return null;
        }

        throw ValidationException::withMessages([
            'listing' => 'The shop does not know how to sell that.',
        ]);
    }

    /**
     * A Protection Card: the Corporation pays, and the Corporation holds it.
     *
     * The buyer is a character rather than a Corporation because somebody stood
     * at the counter, and "who spent our Credits?" is a question the ledger
     * should be able to answer. Which Corporation is read off them rather than
     * asked for - a Security player has exactly one.
     */
    protected function sellProtectionCard(
        ShopListing $listing,
        ProtectionCardType $card,
        Character $buyer,
        ?User $actor,
    ): Corporation {
        if ($buyer->role !== CharacterRole::Security) {
            throw ValidationException::withMessages([
                'character_id' => sprintf(
                    '%s is %s, and 3.3.3 puts the shop list in the Security player\'s hands.',
                    $buyer->name,
                    $buyer->role->label(),
                ),
            ]);
        }

        $corporation = $buyer->corporation;

        if ($corporation === null) {
            throw ValidationException::withMessages([
                'character_id' => sprintf('%s does not belong to a Corporation.', $buyer->name),
            ]);
        }

        $this->charge($corporation, Tracker::CorporationCredits, $listing->price, $corporation->name, sprintf(
            'Bought %s from the shop',
            $card->name,
        ), $actor);

        $this->defence->giveCopy($corporation, $card);

        return $corporation;
    }

    /**
     * An Equipment card: the Runner pays out of their own purse and carries it.
     */
    protected function sellEquipment(
        ShopListing $listing,
        EquipmentCardType $card,
        Character $buyer,
        ?User $actor,
    ): void {
        // The market is the Runners' (2.1), and the reason is mechanical
        // rather than a ruling: this bills the buyer's *own* Credits, and a
        // Corporate seat has none - they spend their Corporation's. Note this
        // is no longer the refusal
        // App\Http\Controllers\Control\EquipmentHoldingController makes, which
        // has gone: anybody may *hold* an Equipment card, because 2.1 has
        // Runners buying them "from other players" and a CEO may well be the
        // player they bought it from. Control hands one over there.
        if (! in_array($buyer->role, [CharacterRole::Runner, CharacterRole::Freelancer], true)) {
            throw ValidationException::withMessages([
                'character_id' => sprintf(
                    '%s is %s, and the market is bought from out of a purse of your own. Hand them the card instead.',
                    $buyer->name,
                    $buyer->role->label(),
                ),
            ]);
        }

        $this->charge($buyer, Tracker::CharacterCredits, $listing->price, $buyer->name, sprintf(
            'Bought %s from the market',
            $card->name,
        ), $actor);

        $this->equipment->giveCopy($buyer, $card);
    }

    /**
     * Take the price off a purse, refusing if it will not cover it.
     *
     * Read from the database rather than off the model, which is the rule
     * Corporation::researchPointsIn() exists for: a service holding a
     * Corporation loaded before the last tracker write would check a stale
     * number. Credits have no floor - they are deliberately unbounded so
     * Control can record a debt - so an unchecked overspend would not be
     * clamped, it would simply go through.
     */
    protected function charge(
        Model $payer,
        Tracker $tracker,
        int $price,
        string $payerName,
        string $reason,
        ?User $actor,
    ): void {
        if ($price === 0) {
            return;
        }

        $credits = (int) $payer->newQuery()
            ->lockForUpdate()
            ->whereKey($payer->getKey())
            ->value('credits');

        if ($credits < $price) {
            throw ValidationException::withMessages([
                'credits' => sprintf(
                    '%s has %d Credit(s) and the price is %d.',
                    $payerName,
                    $credits,
                    $price,
                ),
            ]);
        }

        $this->trackers->adjust($payer, $tracker, -$price, $reason, $actor);
    }

    /**
     * @param  ProtectionCardType|EquipmentCardType  $card
     */
    protected function guardCardBelongsToGame(Game $game, Model $card): void
    {
        if ($card->getAttribute('game_id') !== $game->id) {
            throw ValidationException::withMessages([
                'stockable_id' => 'That card belongs to another game.',
            ]);
        }
    }
}
