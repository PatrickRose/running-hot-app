<?php

namespace App\Support;

use App\Enums\CharacterRole;
use App\Enums\EquationSide;
use App\Enums\ResearchEquationStatus;
use App\Enums\ResearchSuit;
use App\Enums\TechnologyHoldingStatus;
use App\Enums\TechnologyOrigin;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\ResearchCard;
use App\Models\ResearchEquation;
use App\Models\ResearchSeat;
use App\Models\ResearchSession;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Models\User;
use App\Services\ResearchTableService;
use App\Services\TechnologyService;

/**
 * Shapes the research sub-game for the front end (rulebook 3.2).
 *
 * Its own class rather than more of App\Support\GamePresenter, because the
 * research game sends about as much as the rest of the application put
 * together: a hand, a shared pool, a turn order, every equation waiting to be
 * scored, a tech tree with each row's affordability worked out, the cards a
 * Corporation has researched and the deck it plays from. Bolting that onto the
 * presenter that shapes the clock would make one file nobody can hold in their
 * head.
 *
 * Two tiers, and the line between them is the same one the Facility board draws.
 * Everybody may see who is at the research table and whose turn it is, because
 * that is a table in a room. A Corporation's hand, its deck, its Research Point
 * totals and its tech tree progress go only to that Corporation - 3.2.5 makes
 * the amount of Research Points a Corporation has semi-secret, and a hand
 * everybody can read is not a card game.
 *
 * Whether a viewer may *act* is a separate question again, and it belongs to
 * App\Policies\CorporationPolicy: the Research seat plays, and the CEO and
 * Security read.
 */
class ResearchPresenter
{
    public function __construct(
        private readonly ResearchTableService $table,
        private readonly TechnologyService $technologies,
    ) {}

    /**
     * The research game as one player sees it.
     *
     * @return array<string, mixed>
     */
    public function board(Game $game, ?User $user): array
    {
        $own = $user === null ? null : $this->corporationFor($game, $user);
        $session = $this->table->currentSession($game);

        return [
            'turn' => $game->currentTurn()?->number,
            'suits' => $this->suits(),
            'hand_size' => $this->table->handSize(),
            'pool_size' => $this->table->poolSize(),
            // Public: who is at the table, in what order, and whose turn it is.
            'session' => $session === null ? null : $this->session($session, $game, $own),
            'corporations' => $game->corporations()->orderBy('name')->get()
                ->map(fn (Corporation $corporation): array => [
                    'id' => $corporation->id,
                    ...FactionBadge::for($corporation->name),
                    'is_yours' => $own !== null && $own->is($corporation),
                ])->all(),
            'own' => $user !== null && $own !== null
                ? $this->ownResearch($own, $user, $session)
                : null,
        ];
    }

    /**
     * Everything Control needs to run the research table.
     *
     * No tiering here at all: Research Control deals the cards, holds the
     * tokens, prices the custom proposals and settles the trades, so it sees
     * every hand and every pile.
     *
     * @return array<string, mixed>
     */
    public function control(Game $game): array
    {
        $session = $this->table->currentSession($game);
        $corporations = $game->corporations()->orderBy('name')->get();

        return [
            'turn' => $game->currentTurn()?->number,
            'suits' => $this->suits(),
            'origins' => array_map(fn (TechnologyOrigin $origin): array => [
                'value' => $origin->value,
                'label' => $origin->label(),
                'default_discount_percent' => $origin->defaultDiscountPercent(),
            ], TechnologyOrigin::all()),
            'session' => $session === null ? null : $this->session($session, $game, null),
            'public_deck_remaining' => $this->table->deckCount($game, null),
            'corporations' => $corporations->map(fn (Corporation $corporation): array => [
                'id' => $corporation->id,
                ...FactionBadge::for($corporation->name),
                'points' => $corporation->researchPoints(),
                'deck_remaining' => $this->table->deckCount($game, $corporation),
                'hand' => $this->cards($this->table->hand($corporation)),
                'deck' => $this->cards($this->table->deck($game, $corporation)),
                'facilities' => $this->facilities($corporation),
                'holdings' => $this->holdings($corporation),
                'researchers' => $corporation->characters()
                    ->where('role', CharacterRole::Research)
                    ->pluck('name')
                    ->all(),
            ])->all(),
            'equations' => $this->equations(
                $game->researchEquations()
                    ->with('corporation')
                    ->orderByDesc('id')
                    ->limit(60)
                    ->get(),
                withCorporation: true,
            ),
            'technologies' => $game->technologyTypes()
                ->with('requiredFacilityType', 'corporation')
                ->orderBy('tree')
                ->orderBy('code')
                ->get()
                ->map(fn (TechnologyType $type): array => $this->technologySummary($type))
                ->all(),
        ];
    }

    /**
     * The four suits, with the character that draws each one's icon.
     *
     * @return array<int, array{value: string, label: string, glyph: string}>
     */
    public function suits(): array
    {
        return array_map(fn (ResearchSuit $suit): array => [
            'value' => $suit->value,
            'label' => $suit->label(),
            'glyph' => $suit->glyph(),
        ], ResearchSuit::all());
    }

    /**
     * The sitting: the shared pool, the turn order, and how much deck is left.
     *
     * All public. The pool is face up on the table and the order is announced,
     * so there is nothing here a player at the table would not already know.
     *
     * @return array<string, mixed>
     */
    private function session(ResearchSession $session, Game $game, ?Corporation $own): array
    {
        $seats = $session->seats()->with('corporation')->orderBy('order')->get();

        return [
            'id' => $session->id,
            'open' => $session->isOpen(),
            'turn' => $session->turn?->number,
            'pool' => $this->cards($this->table->pool($game)),
            'public_deck_remaining' => $this->table->deckCount($game, null),
            'current_order' => $session->current_order,
            'is_your_turn' => $own !== null && $session->isTurnOf($own),
            'seats' => $seats->map(fn (ResearchSeat $seat): array => [
                'corporation_id' => $seat->corporation_id,
                ...FactionBadge::for($seat->corporation->name),
                'order' => $seat->order,
                'playing' => $seat->isPlaying(),
                'left_reason' => $seat->left_reason,
                'is_turn' => $session->current_order === $seat->order,
                'is_yours' => $own !== null && $own->id === $seat->corporation_id,
                // Public, and deliberately: a deck running low is visible at the
                // table, and it is how everyone knows the game is nearly over.
                'deck_remaining' => $this->table->deckCount($game, $seat->corporation),
            ])->all(),
        ];
    }

    /**
     * One Corporation's own half of the research game, in full.
     *
     * @return array<string, mixed>
     */
    private function ownResearch(Corporation $corporation, User $user, ?ResearchSession $session): array
    {
        $canPlay = $user->can('research', $corporation);
        $seat = $session?->seats()->where('corporation_id', $corporation->id)->first();

        return [
            'id' => $corporation->id,
            ...FactionBadge::for($corporation->name),
            // Whether this player may act, or only read. The Research seat acts.
            'can_play' => $canPlay,
            'points' => $corporation->researchPoints(),
            'hand' => $this->cards($this->table->hand($corporation)),
            'deck' => $this->cards($this->table->deck($corporation->game, $corporation)),
            'deck_remaining' => $this->table->deckCount($corporation->game, $corporation),
            'seated' => $seat !== null,
            'playing' => $seat?->isPlaying() ?? false,
            'left_reason' => $seat?->left_reason,
            'is_your_turn' => $session !== null && $session->isTurnOf($corporation),
            'pending_equations' => $this->equations(
                $corporation->researchEquations()
                    ->where('status', ResearchEquationStatus::Pending)
                    ->orderBy('id')
                    ->get(),
            ),
            'scored_equations' => $this->equations(
                $corporation->researchEquations()
                    ->where('status', '!=', ResearchEquationStatus::Pending)
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get(),
            ),
            'facilities' => $this->facilities($corporation),
            'holdings' => $this->holdings($corporation),
            'tree' => $this->tree($corporation),
        ];
    }

    /**
     * A Corporation's tech tree, with each row's own answer to "can we?".
     *
     * Worked out here rather than in the browser because all three answers need
     * the game: what is affordable needs the points, what is unlocked needs
     * every technology already researched, and what a claimed copy is worth
     * needs the discount on that particular card.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tree(Corporation $corporation): array
    {
        $points = $corporation->researchPoints();

        /** @var array<int, array<int, TechnologyHolding>> $claims */
        $claims = $corporation->technologyHoldings()
            ->where('status', TechnologyHoldingStatus::Claimed)
            ->get()
            ->groupBy('technology_type_id')
            ->map(fn ($group) => $group->all())
            ->all();

        $researched = $corporation->technologyHoldings()
            ->researched()
            ->get()
            ->countBy('technology_type_id');

        return $this->technologies->treeFor($corporation)
            ->map(function (TechnologyType $type) use ($corporation, $points, $claims, $researched): array {
                $missing = $this->technologies->missingPrerequisites($corporation, $type);
                $cost = $this->technologies->costFor($type);

                return [
                    ...$this->technologySummary($type),
                    'cost' => $cost,
                    'affordable' => $this->affordable($points, $cost),
                    'missing_prerequisites' => $missing,
                    'researched_count' => (int) ($researched[$type->id] ?? 0),
                    // A copy or a theft in hand: what it is, and what paying for
                    // it would cost with its discount applied.
                    'claims' => array_map(fn (TechnologyHolding $claim): array => [
                        'id' => $claim->id,
                        'origin' => $claim->origin->value,
                        'origin_label' => $claim->origin->label(),
                        'discount_percent' => $claim->discount_percent,
                        'cost' => $this->technologies->costFor($claim->technologyType, $claim->discount_percent),
                        'facility' => $claim->facility?->name,
                    ], $claims[$type->id] ?? []),
                ];
            })->all();
    }

    /**
     * The printed facts about a technology, shared by both pages.
     *
     * @return array<string, mixed>
     */
    private function technologySummary(TechnologyType $type): array
    {
        return [
            'id' => $type->id,
            'code' => $type->code,
            'image_path' => $type->imagePath(),
            'back_image_path' => $type->backImagePath(),
            'name' => $type->name,
            'tree' => $type->tree,
            'corporation' => $type->corporation?->name,
            'description' => $type->description,
            'effect' => $type->effect,
            'cost' => $type->cost(),
            'is_free' => $type->isFree(),
            'prerequisites' => $type->prerequisites,
            'required_facility_type' => $type->requiredFacilityType?->name,
            'copy_strength' => $type->copy_strength,
            'destroy_strength' => $type->destroy_strength,
            'split_group' => $type->split_group,
            'split_piece' => $type->split_piece,
            'split_pieces' => $type->split_pieces,
            'is_deck_customisation' => $type->isDeckCustomisation(),
            'deck_grant' => $this->deckGrant($type),
        ];
    }

    /**
     * What a deck customisation row grants, with its marking in the words the
     * card prints rather than the key the rules match on.
     *
     * @return array<string, mixed>|null
     */
    private function deckGrant(TechnologyType $type): ?array
    {
        $grant = $type->deckGrant();

        if ($grant === null) {
            return null;
        }

        return [
            ...$grant,
            'restriction' => $grant['restriction']?->label(),
            'restriction_note' => $grant['restriction']?->description(),
        ];
    }

    /**
     * Where a Corporation can put a technology, and how full each place is.
     *
     * Storage is per Facility and comes from the count of Corporate Facilities,
     * so every Facility in the list has the same capacity - it is repeated on
     * each because what the page needs to say is "3 of 6 in Owlerton", one
     * Facility at a time.
     *
     * @return array<int, array<string, mixed>>
     */
    private function facilities(Corporation $corporation): array
    {
        $turn = $corporation->game->currentTurn()?->number;

        return $corporation->facilities()
            ->with('facilityType')
            ->orderBy('name')
            ->get()
            ->map(fn (Facility $facility): array => [
                'id' => $facility->id,
                'name' => $facility->name,
                'facility_type' => $facility->facilityType->name,
                'facility_type_id' => $facility->facility_type_id,
                'available' => $facility->isAvailableOnTurn($turn),
                'stored' => $this->technologies->storedIn($facility),
                'capacity' => $this->technologies->capacityFor($facility),
            ])->all();
    }

    /**
     * The technology cards a Corporation has.
     *
     * @return array<int, array<string, mixed>>
     */
    private function holdings(Corporation $corporation): array
    {
        return $corporation->technologyHoldings()
            ->with('technologyType', 'facility')
            ->orderBy('id')
            ->get()
            ->map(fn (TechnologyHolding $holding): array => [
                'id' => $holding->id,
                'technology_type_id' => $holding->technology_type_id,
                'name' => $holding->technologyType->name,
                'code' => $holding->technologyType->code,
                'image_path' => $holding->technologyType->imagePath(),
                'back_image_path' => $holding->technologyType->backImagePath(),
                'effect' => $holding->technologyType->effect,
                'status' => $holding->status->value,
                'status_label' => $holding->status->label(),
                'origin' => $holding->origin->value,
                'origin_label' => $holding->origin->label(),
                'discount_percent' => $holding->discount_percent,
                'facility_id' => $holding->facility_id,
                'facility' => $holding->facility?->name,
                'paid' => $holding->paid(),
                // The split rules of 3.2.7 in one boolean: a claimed copy is
                // paper, and a stolen piece of a four-part technology does
                // nothing until its thief has all four.
                'usable' => $this->technologies->isUsable($holding),
                'split_group' => $holding->technologyType->split_group,
                'split_piece' => $holding->technologyType->split_piece,
                'split_pieces' => $holding->technologyType->split_pieces,
                'notes' => $holding->notes,
            ])->all();
    }

    /**
     * @param  iterable<int, ResearchCard>  $cards
     * @return array<int, array<string, mixed>>
     */
    private function cards(iterable $cards): array
    {
        $shaped = [];

        foreach ($cards as $card) {
            $shaped[] = [
                'id' => $card->id,
                'suit' => $card->suit?->value,
                'suit_label' => $card->suit?->label(),
                'glyph' => $card->glyph(),
                'value' => $card->value,
                'wild' => $card->isWild(),
                'label' => $card->label(),
                // The words on the card, and what they do. Both, because "No
                // single" is not self-explanatory and the tile has room for
                // three characters: the label is drawn and the note is what a
                // screen reader and a tooltip get.
                'restriction' => $card->restriction?->label(),
                'restriction_note' => $card->restriction?->description(),
                'zone' => $card->zone->value,
                'zone_label' => $card->zone->label(),
            ];
        }

        return $shaped;
    }

    /**
     * @param  iterable<int, ResearchEquation>  $equations
     * @return array<int, array<string, mixed>>
     */
    private function equations(iterable $equations, bool $withCorporation = false): array
    {
        $shaped = [];

        foreach ($equations as $equation) {
            $shaped[] = [
                'id' => $equation->id,
                'status' => $equation->status->value,
                'status_label' => $equation->status->label(),
                'corporation' => $withCorporation ? $equation->corporation->name : null,
                'corporation_id' => $equation->corporation_id,
                'turn' => $equation->turn?->number,
                'left' => $equation->left_cards,
                'right' => $equation->right_cards,
                'left_label' => $equation->describeSide(EquationSide::Left),
                'right_label' => $equation->describeSide(EquationSide::Right),
                'left_sum' => $equation->left_sum,
                'right_sum' => $equation->right_sum,
                'cards_per_side' => $equation->cards_per_side,
                'balanced' => $equation->balanced,
                'bonus' => $equation->bonus,
                // Which suits each side may be taken as. An all-wild set may be
                // any of the four, so this cannot be read off the cards without
                // reimplementing the rule in the browser.
                'left_suits' => $this->suitValues($equation, EquationSide::Left),
                'right_suits' => $this->suitValues($equation, EquationSide::Right),
                'bonus_suits' => array_map(
                    fn (ResearchSuit $suit): string => $suit->value,
                    $equation->toEquation()->suitsUsed(),
                ),
                'scored_side' => $equation->scored_side?->value,
                'scored_suit' => $equation->scored_suit?->value,
                'awards' => $equation->awards,
                'scored_at' => $equation->scored_at?->toIso8601String(),
                'notes' => $equation->notes,
            ];
        }

        return $shaped;
    }

    /**
     * @return array<int, string>
     */
    private function suitValues(ResearchEquation $equation, EquationSide $side): array
    {
        return array_map(
            fn (ResearchSuit $suit): string => $suit->value,
            $equation->toEquation()->suitsFor($side),
        );
    }

    /**
     * @param  array<string, int>  $points
     * @param  array<string, int>  $cost
     */
    private function affordable(array $points, array $cost): bool
    {
        foreach ($cost as $suit => $amount) {
            if (($points[$suit] ?? 0) < $amount) {
                return false;
            }
        }

        return true;
    }

    /**
     * The Corporation this player sits in.
     *
     * The Research seat first, because that is whose page this is - and then any
     * Corporate seat, because the CEO and Security see their Corporation's
     * research too. What stops them acting on it is the policy, not this.
     */
    private function corporationFor(Game $game, User $user): ?Corporation
    {
        return $user->corporationIn($game, CharacterRole::Research)
            ?? $user->corporationIn($game);
    }
}
