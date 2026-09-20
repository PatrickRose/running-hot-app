<?php

namespace App\Support;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\PhaseType;
use App\Enums\ShopListingStatus;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\ShopListing;
use App\Models\ShopPurchase;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What the shop looks like to whoever is standing in front of it (rulebook
 * 3.3.3, 2.1).
 *
 * Its own class rather than more of GamePresenter, for the reason
 * ResearchPresenter is: the shop has two counters, two kinds of buyer, two
 * purses and a stock ledger, and none of it is about a Facility.
 *
 * The line it draws is the same one 3.3.3 draws. The Protection Card list is
 * handed to the Security players, so it goes to the people holding a Corporate
 * seat; the market is the Runners', so it goes to them. That is not fussiness
 * about secrecy - a price list is not one of the things 3.4.2 keeps Secret -
 * but it is what stops the shop handing every Runner a catalogue of the cards
 * they are about to meet, which is reconnaissance the rulebook makes them pay
 * for. Control sees both, as Control sees everything.
 *
 * Nothing here decides whether a purchase would be allowed. The page draws what
 * the server already knows - the price, what is left, what you hold and what
 * you can afford - and the refusal, if there is one, still comes from
 * App\Services\ShopService when the button is pressed.
 */
class ShopPresenter
{
    /**
     * The shop as a player sees it.
     *
     * Both counters are answered, and either may be null: a Security player has
     * no business at the Runners' market and a Runner is not handed the
     * Protection Card list. Somebody holding seats on both sides - which
     * happens, because a user claims characters rather than a side - gets both.
     *
     * @return array<string, mixed>
     */
    public function forPlayer(Game $game, ?User $user): array
    {
        $characters = $user === null
            ? collect()
            : $game->characters()->where('user_id', $user->id)->with('corporation')->get();

        $isControl = $user?->isControlFor($game) ?? false;

        $listings = $this->listingsFor($game, includeWithdrawn: false);

        $corporate = $characters->filter(fn (Character $c): bool => $c->role->isCorporate());
        $runners = $characters->filter(
            fn (Character $c): bool => in_array($c->role, [CharacterRole::Runner, CharacterRole::Freelancer], true),
        );

        $seesProtection = $isControl || $corporate->isNotEmpty();
        $seesEquipment = $isControl || $runners->isNotEmpty();

        return [
            // Whether the counter is open at all. The shop runs during Setup
            // (3.3.3, 2.1), so the rest of the turn it is a price list to read
            // and plan against rather than to buy from.
            'open' => $this->isOpen($game),
            'phase' => $game->currentPhase()?->type->label(),
            'is_control' => $isControl,
            'protection' => ! $seesProtection ? null : [
                'listings' => $this->ofFamily($listings, 'protection'),
                // Only the Security seats. The CEO and the Research player read
                // the list and do not buy from it, which is the same boundary
                // the Facility board draws.
                'buyers' => $this->protectionBuyers(
                    $characters->filter(fn (Character $c): bool => $c->role === CharacterRole::Security),
                    $listings,
                ),
            ],
            'equipment' => ! $seesEquipment ? null : [
                'listings' => $this->ofFamily($listings, 'equipment'),
                'buyers' => $this->equipmentBuyers($runners, $listings),
            ],
        ];
    }

    /**
     * The shop as Control runs it: every line including the withdrawn ones, the
     * cards not yet on the list, and what has been sold.
     *
     * @return array<string, mixed>
     */
    public function forControl(Game $game): array
    {
        $listings = $this->listingsFor($game, includeWithdrawn: true);

        $listed = $game->shopListings()->get()
            ->groupBy('stockable_type')
            ->map(fn (Collection $rows): array => $rows->pluck('stockable_id')->all())
            ->all();

        return [
            'open' => $this->isOpen($game),
            'phase' => $game->currentPhase()?->type->label(),
            'listings' => $listings,
            'unlisted' => [
                'protection' => $this->unlistedProtection($game, $listed),
                'equipment' => $this->unlistedEquipment($game, $listed),
            ],
            'purchases' => $this->purchases($game),
            // Who Control can buy on behalf of. A player will phone one in, and
            // there is no sense making Control claim their seat to do it.
            'buyers' => [
                'protection' => $this->buyerOptions($game, [CharacterRole::Security]),
                'equipment' => $this->buyerOptions($game, [CharacterRole::Runner, CharacterRole::Freelancer]),
            ],
            'statuses' => array_map(
                fn (ShopListingStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                ShopListingStatus::cases(),
            ),
        ];
    }

    /**
     * Whether a purchase would be in time.
     *
     * Control is not asked about, because Control is never out of time - this
     * is what the players' page draws, and their own refusal comes from
     * App\Policies\ShopListingPolicy either way.
     */
    protected function isOpen(Game $game): bool
    {
        return $game->status === GameStatus::Running
            && $game->currentPhase()?->type === PhaseType::Setup;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function listingsFor(Game $game, bool $includeWithdrawn): array
    {
        $query = $game->shopListings()->with('stockable');

        if (! $includeWithdrawn) {
            $query->where('status', '!=', ShopListingStatus::Withdrawn);
        }

        $listings = [];

        foreach ($query->withCount('purchases')->get() as $row) {
            $listing = $this->listing($row);

            if ($listing !== null) {
                $listings[] = $listing;
            }
        }

        // Cards you can buy first, then the rumoured ones, then whatever
        // Control has taken down - which is the order somebody shopping reads
        // them in. Written out rather than given to sortBy as a list of
        // closures, which reads those as comparators and silently sorts by
        // nothing.
        usort(
            $listings,
            fn (array $a, array $b): int => [$this->listOrder($a), $a['card']['name']]
                <=> [$this->listOrder($b), $b['card']['name']],
        );

        return $listings;
    }

    /**
     * One counter's lines out of the whole list.
     *
     * @param  array<int, array<string, mixed>>  $listings
     * @return array<int, array<string, mixed>>
     */
    protected function ofFamily(array $listings, string $family): array
    {
        return array_values(array_filter(
            $listings,
            fn (array $listing): bool => $listing['family'] === $family,
        ));
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    protected function listOrder(array $listing): int
    {
        return match ($listing['status']) {
            ShopListingStatus::OnSale->value => 0,
            ShopListingStatus::Rumoured->value => 1,
            default => 2,
        };
    }

    /**
     * One line of the list.
     *
     * Null where the card behind it has been deleted from the catalogue, which
     * a cascade normally prevents - but a listing with nothing to sell must
     * never be drawn as a card called "null".
     *
     * @return array<string, mixed>|null
     */
    protected function listing(ShopListing $listing): ?array
    {
        $card = $listing->stockable;

        if ($card === null) {
            return null;
        }

        $family = $listing->isProtectionCard() ? 'protection' : 'equipment';

        return [
            'id' => $listing->id,
            'family' => $family,
            'card' => $card instanceof ProtectionCardType
                ? $this->protectionCard($card)
                : $this->equipmentCard($card),
            'price' => $listing->price,
            // Null is a line that never runs out, and the page says so in
            // words rather than drawing an empty cell.
            'stock' => $listing->stock,
            'status' => $listing->status->value,
            'status_label' => $listing->status->label(),
            'available' => $listing->isAvailable(),
            'sold_out' => $listing->isSoldOut(),
            'sold_count' => (int) $listing->getAttribute('purchases_count'),
            'notes' => $listing->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function protectionCard(ProtectionCardType $card): array
    {
        return [
            'id' => $card->id,
            'code' => $card->code,
            'name' => $card->name,
            'image_path' => $card->imagePath(),
            'kind' => $card->kind->value,
            'kind_label' => $card->kind->label(),
            'kind_glyph' => $card->kind->glyph(),
            'challenge' => $card->challenge,
            'consequence' => $card->consequence,
            'charge_cost' => $card->charge_cost,
            'charge_consequence' => $card->charge_consequence,
            // The catalogue's own word on the card, drawn beside the shop's so
            // Control can see the two agree. They are different questions: this
            // is how a card can be got at all, and the listing's status is what
            // the shop is doing about it this game.
            'availability' => $card->availability->value,
            'availability_label' => $card->availability->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function equipmentCard(EquipmentCardType $card): array
    {
        return [
            'id' => $card->id,
            'code' => $card->code,
            'name' => $card->name,
            'image_path' => $card->imagePath(),
            'category' => $card->category->value,
            'category_label' => $card->category->label(),
            'category_glyph' => $card->category->glyph(),
            'effect' => $card->effect,
        ];
    }

    /**
     * The Security seats this player holds, and what their Corporations can
     * afford.
     *
     * The purse is the Corporation's rather than theirs, because that is what
     * buys a Protection Card - so the figure on screen is the one that will be
     * checked, and a Security player with nothing in their own pocket is not
     * told they cannot shop.
     *
     * @param  Collection<int, Character>  $characters
     * @param  array<int, array<string, mixed>>  $listings
     * @return array<int, array<string, mixed>>
     */
    protected function protectionBuyers(Collection $characters, array $listings): array
    {
        return $characters
            ->filter(fn (Character $c): bool => $c->corporation !== null)
            ->map(function (Character $character) use ($listings): array {
                $corporation = $character->corporation;

                return [
                    'character_id' => $character->id,
                    'name' => $character->name,
                    // The Corporation's name rather than theirs, because the
                    // Corporation is what pays - so the figure beside it is
                    // the one that will be checked.
                    'purse_name' => $corporation->name,
                    'credits' => $corporation->credits,
                    'held' => $this->heldAgainst(
                        $listings,
                        'protection',
                        $this->copiesInHand($corporation),
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Character>  $characters
     * @param  array<int, array<string, mixed>>  $listings
     * @return array<int, array<string, mixed>>
     */
    protected function equipmentBuyers(Collection $characters, array $listings): array
    {
        return $characters
            ->map(fn (Character $character): array => [
                'character_id' => $character->id,
                'name' => $character->name,
                // A Runner buys out of their own pocket, so the purse is named
                // for them.
                'purse_name' => $character->name,
                'credits' => $character->credits,
                'held' => $this->heldAgainst(
                    $listings,
                    'equipment',
                    $this->equipmentInHand($character),
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * How many copies of each listed card the buyer already has, keyed by
     * listing id.
     *
     * The hand is read in one query per buyer and then looked up, rather than
     * asked for a card at a time: a shop with twenty lines on it would
     * otherwise be twenty queries a buyer, and the page draws every line.
     *
     * @param  array<int, array<string, mixed>>  $listings
     * @param  array<int, int>  $inHand  copies, keyed by card type id
     * @return array<int, int>
     */
    protected function heldAgainst(array $listings, string $family, array $inHand): array
    {
        $held = [];

        foreach ($listings as $listing) {
            if ($listing['family'] !== $family) {
                continue;
            }

            $held[$listing['id']] = $inHand[$listing['card']['id']] ?? 0;
        }

        return $held;
    }

    /**
     * A Corporation's uninstalled Protection Cards, by card type id.
     *
     * In hand rather than owned outright, because that is what a purchase adds
     * to and what 3.3.4 spends: a card standing in a Facility is not one you
     * can install somewhere else.
     *
     * @return array<int, int>
     */
    protected function copiesInHand(Corporation $corporation): array
    {
        return $corporation->protectionCardHoldings()
            ->pluck('copies', 'protection_card_type_id')
            ->map(fn (mixed $copies): int => (int) $copies)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    protected function equipmentInHand(Character $character): array
    {
        return $character->equipmentHoldings()
            ->pluck('copies', 'equipment_card_type_id')
            ->map(fn (mixed $copies): int => (int) $copies)
            ->all();
    }

    /**
     * Protection Cards not yet on the list - all of them.
     *
     * Research-only cards used to be left out, on 3.3.3's "will not be
     * available for general sale". They are offered now, because the shop is
     * how Control hands a card over at a price and that sentence is about the
     * ordinary run of the game rather than about what Control may do. Each one
     * still carries its availability, so a card Control might not have meant to
     * put out says what it is rather than being silently missing.
     *
     * Shaped by protectionCard() rather than by a smaller list of its own, so
     * the picker can draw the card Control has chosen before they price it.
     * Pricing a card you cannot see is guesswork, and the artwork is what
     * somebody at the table will be holding - a second, thinner shape here
     * would be one more place for the two to disagree about what a card is.
     *
     * @param  array<string, array<int, int>>  $listed
     * @return array<int, array<string, mixed>>
     */
    protected function unlistedProtection(Game $game, array $listed): array
    {
        $taken = $listed[(new ProtectionCardType)->getMorphClass()] ?? [];

        return $game->protectionCardTypes()
            ->whereNotIn('id', $taken)
            ->orderBy('kind')
            ->orderBy('name')
            ->get()
            ->map(fn (ProtectionCardType $card): array => $this->protectionCard($card))
            ->all();
    }

    /**
     * @param  array<string, array<int, int>>  $listed
     * @return array<int, array<string, mixed>>
     */
    protected function unlistedEquipment(Game $game, array $listed): array
    {
        $taken = $listed[(new EquipmentCardType)->getMorphClass()] ?? [];

        return $game->equipmentCardTypes()
            ->whereNotIn('id', $taken)
            ->orderBy('name')
            ->get()
            ->map(fn (EquipmentCardType $card): array => $this->equipmentCard($card))
            ->all();
    }

    /**
     * What has left the shop, newest first.
     *
     * Capped, because this is a running game's till roll rather than an
     * archive: Control wants the last few minutes of it, and the rest is in the
     * database for afterwards.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function purchases(Game $game, int $limit = 60): array
    {
        return $game->shopPurchases()
            ->with(['listing.stockable', 'character', 'corporation', 'phase.turn'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (ShopPurchase $purchase): array => [
                'id' => $purchase->id,
                'card_name' => $purchase->listing?->stockable?->getAttribute('name') ?? 'A card no longer listed',
                'buyer_name' => $purchase->character->name ?? 'A character no longer in the game',
                'corporation_name' => $purchase->corporation?->name,
                'price_paid' => $purchase->price_paid,
                'turn' => $purchase->phase?->turn?->number,
                'phase' => $purchase->phase?->type->label(),
                'bought_at' => $purchase->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Characters Control may buy on behalf of.
     *
     * @param  array<int, CharacterRole>  $roles
     * @return array<int, array<string, mixed>>
     */
    protected function buyerOptions(Game $game, array $roles): array
    {
        return $game->characters()
            ->whereIn('role', $roles)
            ->with(['corporation', 'gang'])
            ->orderBy('name')
            ->get()
            ->map(fn (Character $character): array => [
                'character_id' => $character->id,
                'name' => $character->name,
                'role_label' => $character->role->label(),
                'team' => $character->corporation->name ?? $character->gang?->name,
                'credits' => $character->credits,
                'purse_credits' => $character->corporation->credits ?? $character->credits,
            ])
            ->all();
    }
}
