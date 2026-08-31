<?php

namespace App\Services;

use App\Enums\ResearchSuit;
use App\Enums\TechnologyHoldingStatus;
use App\Enums\TechnologyOrigin;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spending Research Points on the tech tree (rulebook 3.2.2), and everything
 * that happens to a technology afterwards (3.2.5 to 3.2.7).
 *
 * The tree itself is a catalogue - technology_types - and Control edits it
 * freely, including mid-game (3.2.4). This is the half that moves: which
 * Corporation has paid for what, which Facility is storing it, and what a card
 * that arrived by some other route is worth off the price.
 *
 * Three rules here are the ones worth reading twice.
 *
 * A technology has to be housed. "You must then place this in one of your
 * Facilities", and footnote 7 makes it a bar on researching at all rather than a
 * thing to sort out later: a Corporation with nowhere to put a technology may
 * not research it. Storage per Facility is 2 for every Corporate Facility the
 * Corporation owns, which App\Services\FacilityDefenceService derives - so
 * building a Corporate Facility widens every Facility at once.
 *
 * A copy is a discount, not a technology. A card a Run brought back or Research
 * Control made is Claimed: it sits in a Facility, it takes up storage
 * (footnote 8 to 3.2.6), and it does nothing until the Corporation pays the
 * discounted cost. The discount rounds the resulting cost *up*, which 3.2.6
 * says in as many words.
 *
 * A split technology's owner is not its thief. The Corporation that researched
 * one works it holding any single piece; a Corporation that stole or copied it
 * needs every piece before it works at all (3.2.7). That is the only place in
 * the application where who came by something matters more than what it is.
 */
class TechnologyService
{
    public function __construct(
        private readonly TrackerService $trackers,
        private readonly FacilityDefenceService $defence,
    ) {}

    /**
     * What a technology costs, with a discount applied (rulebook 3.2.6).
     *
     * "Rounding the resulting cost up" - so a 5 at 50% off is 3, not 2. Applied
     * suit by suit, because that is how the cost is printed and how the points
     * are handed over.
     *
     * @return array<string, int>
     */
    public function costFor(TechnologyType $technology, int $discountPercent = 0): array
    {
        $discount = max(0, min(100, $discountPercent));
        $cost = [];

        foreach ($technology->cost() as $suit => $amount) {
            $cost[$suit] = (int) ceil($amount * (100 - $discount) / 100);
        }

        return $cost;
    }

    /**
     * The technologies a Corporation may research: its own tree plus the common
     * one (rulebook 3.2.2).
     *
     * @return Collection<int, TechnologyType>
     */
    public function treeFor(Corporation $corporation): Collection
    {
        return $corporation->game
            ->technologyTypes()
            ->where(fn ($query) => $query
                ->whereNull('corporation_id')
                ->orWhere('corporation_id', $corporation->id))
            ->with('requiredFacilityType')
            ->orderBy('tree')
            ->orderBy('code')
            ->get();
    }

    /**
     * The prerequisite titles a Corporation is still missing (rulebook 3.2.2).
     *
     * Prerequisites are the titles printed on the card rather than foreign keys,
     * because a title is what a Research player shows Research Control
     * (footnote 6) and because Control may add a technology others already name.
     *
     * A split technology counts by its group as well as by its own name: a
     * Corporation that researched "Power (Part 1/4)" has Power, since 3.2.7 lets
     * its owner work the technology holding any one piece.
     *
     * @return array<int, string>
     */
    public function missingPrerequisites(Corporation $corporation, TechnologyType $technology): array
    {
        $required = $technology->prerequisites;

        if ($required === []) {
            return [];
        }

        $held = [];

        foreach ($this->researchedTypes($corporation) as $type) {
            $held[$type->name] = true;

            if ($type->split_group !== null) {
                $held[$type->split_group] = true;
            }
        }

        return array_values(array_filter(
            $required,
            fn (string $title): bool => ! isset($held[$title]),
        ));
    }

    public function prerequisitesMet(Corporation $corporation, TechnologyType $technology): bool
    {
        return $this->missingPrerequisites($corporation, $technology) === [];
    }

    /**
     * How many technologies a Facility is storing (rulebook 3.2.2).
     *
     * A claimed copy counts. Footnote 8 to 3.2.6 is explicit about it, and it is
     * the reason a Corporation cannot use a Facility as a warehouse for cards it
     * has not paid for.
     */
    public function storedIn(Facility $facility): int
    {
        return $facility->technologyHoldings()->standing()->count();
    }

    /**
     * How many a Facility can store: 2 for every Corporate Facility the
     * Corporation owns.
     */
    public function capacityFor(Facility $facility): int
    {
        return $this->defence->technologyCapacityPerFacility($facility->corporation);
    }

    /**
     * Research a technology and house it (rulebook 3.2.2).
     *
     * A claim is a card the Corporation already has - a copy, or one taken off a
     * rival - and paying for it flips that card rather than creating a second
     * one. Its discount is what the claim is worth.
     */
    public function research(
        Corporation $corporation,
        TechnologyType $technology,
        Facility $facility,
        ?TechnologyHolding $claim = null,
        ?User $actor = null,
    ): TechnologyHolding {
        return DB::transaction(function () use ($corporation, $technology, $facility, $claim, $actor): TechnologyHolding {
            $this->assertOnTree($corporation, $technology);

            if ($technology->isDeckCustomisation()) {
                throw ValidationException::withMessages([
                    'technology_type_id' => $technology->name
                        .' customises your research deck rather than producing a technology.',
                ]);
            }

            $missing = $this->missingPrerequisites($corporation, $technology);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'technology_type_id' => 'Still needed first: '.implode(', ', $missing).'.',
                ]);
            }

            if ($claim !== null) {
                if ($claim->corporation_id !== $corporation->id
                    || $claim->technology_type_id !== $technology->id
                    || $claim->status !== TechnologyHoldingStatus::Claimed) {
                    throw ValidationException::withMessages([
                        'technology_holding_id' => 'That is not a card this Corporation is holding unresearched.',
                    ]);
                }
            }

            $discount = $claim->discount_percent ?? 0;
            $cost = $this->costFor($technology, $discount);

            // Checked before the Facility, so a Corporation is told it cannot
            // afford something before being told where it would have gone.
            $this->assertAffordable($corporation, $cost);

            // A claimed card is already in a Facility, so moving it does not
            // need a second slot - only a card arriving does.
            $this->assertCanStore($corporation, $technology, $facility, ignoring: $claim);

            foreach ($cost as $suitValue => $amount) {
                if ($amount === 0) {
                    continue;
                }

                $this->trackers->adjust(
                    $corporation,
                    ResearchSuit::from($suitValue)->tracker(),
                    -$amount,
                    'Researched '.$technology->name,
                    $actor,
                );
            }

            $attributes = [
                'facility_id' => $facility->id,
                'status' => TechnologyHoldingStatus::Researched,
                'researched_at' => Carbon::now(),
                'paid_cog' => $cost[ResearchSuit::Cog->value],
                'paid_brain' => $cost[ResearchSuit::Brain->value],
                'paid_leaf' => $cost[ResearchSuit::Leaf->value],
                'paid_maths' => $cost[ResearchSuit::Maths->value],
            ];

            if ($claim !== null) {
                $claim->forceFill($attributes)->save();

                return $claim->refresh();
            }

            /** @var TechnologyHolding $holding */
            $holding = $corporation->technologyHoldings()->create([
                'game_id' => $corporation->game_id,
                'technology_type_id' => $technology->id,
                'origin' => TechnologyOrigin::Researched,
                'discount_percent' => 0,
                ...$attributes,
            ]);

            return $holding;
        });
    }

    /**
     * Put an unresearched card into a Corporation's hands (rulebook 3.2.5,
     * 3.2.6).
     *
     * Control's, and deliberately so: sharing goes "to Research Control with the
     * technology in question", and a copy or a theft is the outcome of a Run that
     * Control has just adjudicated. What the card is worth off the price defaults
     * to its kind and can be named outright, because 3.2.6 leaves a copy's
     * discount to "the strength of the copy".
     */
    public function grant(
        Corporation $corporation,
        TechnologyType $technology,
        TechnologyOrigin $origin,
        ?int $discountPercent = null,
        ?Facility $facility = null,
        ?string $notes = null,
    ): TechnologyHolding {
        return DB::transaction(function () use (
            $corporation,
            $technology,
            $origin,
            $discountPercent,
            $facility,
            $notes,
        ): TechnologyHolding {
            if ($technology->game_id !== $corporation->game_id) {
                throw ValidationException::withMessages([
                    'technology_type_id' => 'That technology belongs to a different game.',
                ]);
            }

            if ($facility !== null) {
                $this->assertCanStore($corporation, $technology, $facility);
            }

            /** @var TechnologyHolding $holding */
            $holding = $corporation->technologyHoldings()->create([
                'game_id' => $corporation->game_id,
                'technology_type_id' => $technology->id,
                'facility_id' => $facility?->id,
                'status' => $origin === TechnologyOrigin::Researched
                    ? TechnologyHoldingStatus::Researched
                    : TechnologyHoldingStatus::Claimed,
                'origin' => $origin,
                'discount_percent' => max(0, min(100, $discountPercent ?? $origin->defaultDiscountPercent())),
                'researched_at' => $origin === TechnologyOrigin::Researched ? Carbon::now() : null,
                'notes' => $notes,
            ]);

            return $holding;
        });
    }

    /**
     * Move a card to another of the Corporation's Facilities.
     *
     * A Facility that has been destroyed, or one whose storage is needed for
     * something else, sends its cards somewhere - so this is a real move rather
     * than a correction, and it is checked like one.
     */
    public function place(TechnologyHolding $holding, ?Facility $facility): TechnologyHolding
    {
        if ($facility !== null) {
            $this->assertCanStore(
                $holding->corporation,
                $holding->technologyType,
                $facility,
                ignoring: $holding,
            );
        }

        $holding->forceFill(['facility_id' => $facility?->id])->save();

        return $holding;
    }

    /**
     * Destroy a card a Run got to (rulebook 3.2.6).
     *
     * The row stays: "they may be able to salvage their research afterwards" is
     * a conversation with Control, and what a Corporation once had is worth more
     * than a tidy table.
     */
    public function destroy(TechnologyHolding $holding, ?string $notes = null): TechnologyHolding
    {
        $holding->forceFill([
            'status' => TechnologyHoldingStatus::Destroyed,
            'destroyed_at' => Carbon::now(),
            'notes' => $notes ?? $holding->notes,
        ])->save();

        return $holding;
    }

    /**
     * Undo a destruction, which is the salvage 3.2.6 hints at.
     */
    public function restore(TechnologyHolding $holding): TechnologyHolding
    {
        $holding->forceFill([
            'status' => $holding->researched_at === null
                ? TechnologyHoldingStatus::Claimed
                : TechnologyHoldingStatus::Researched,
            'destroyed_at' => null,
        ])->save();

        return $holding;
    }

    /**
     * Hand Research Points from one Corporation to another (rulebook 3.2.5).
     *
     * "Players may freely trade their Research Points between different
     * Corporations ... by passing over the requisite tokens." So there is no
     * price and no consent to record: one pile goes down and the other goes up.
     * What the application adds is that both movements are in the ledger with
     * the other Corporation named, so a trade can be reconstructed from either
     * end.
     */
    public function transferPoints(
        Corporation $from,
        Corporation $to,
        ResearchSuit $suit,
        int $amount,
        ?User $actor = null,
    ): void {
        if ($from->is($to)) {
            throw ValidationException::withMessages([
                'corporation_id' => 'A Corporation cannot trade with itself.',
            ]);
        }

        if ($from->game_id !== $to->game_id) {
            throw ValidationException::withMessages([
                'corporation_id' => 'Those Corporations are in different games.',
            ]);
        }

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'Trade at least one Research Point.',
            ]);
        }

        $held = $from->researchPointsIn($suit);

        if ($held < $amount) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    '%s has %d %s Research Point(s).',
                    $from->name,
                    $held,
                    $suit->label(),
                ),
            ]);
        }

        DB::transaction(function () use ($from, $to, $suit, $amount, $actor): void {
            $this->trackers->adjust(
                $from,
                $suit->tracker(),
                -$amount,
                'Traded to '.$to->name,
                $actor,
            );

            $this->trackers->adjust(
                $to,
                $suit->tracker(),
                $amount,
                'Traded from '.$from->name,
                $actor,
            );
        });
    }

    /**
     * Whether a card is actually working for the Corporation holding it.
     *
     * False for a claimed copy, which is paper until it is researched, and false
     * for a split technology somebody took a piece of and has not completed
     * (rulebook 3.2.7). The Corporation that researched it themselves needs only
     * the one piece.
     */
    public function isUsable(TechnologyHolding $holding): bool
    {
        if (! $holding->isResearched()) {
            return false;
        }

        $type = $holding->technologyType;

        if (! $type->isSplit() || ! $holding->origin->needsEveryPiece()) {
            return true;
        }

        return $this->piecesHeld($holding->corporation, (string) $type->split_group)
            >= (int) $type->split_pieces;
    }

    /**
     * How many distinct pieces of a split technology a Corporation has
     * researched.
     *
     * Distinct, because two copies of part 3 are not parts 3 and 4.
     */
    public function piecesHeld(Corporation $corporation, string $splitGroup): int
    {
        return $corporation->technologyHoldings()
            ->researched()
            ->whereHas(
                'technologyType',
                fn ($query) => $query->where('split_group', $splitGroup),
            )
            ->with('technologyType')
            ->get()
            ->pluck('technologyType.split_piece')
            ->unique()
            ->count();
    }

    /**
     * The technology types a Corporation has researched and not lost.
     *
     * @return Collection<int, TechnologyType>
     */
    private function researchedTypes(Corporation $corporation): Collection
    {
        /** @var Collection<int, TechnologyType> */
        return TechnologyType::query()
            ->whereIn('id', $corporation->technologyHoldings()
                ->researched()
                ->select('technology_type_id'))
            ->get();
    }

    private function assertOnTree(Corporation $corporation, TechnologyType $technology): void
    {
        if ($technology->game_id !== $corporation->game_id) {
            throw ValidationException::withMessages([
                'technology_type_id' => 'That technology belongs to a different game.',
            ]);
        }

        if ($technology->corporation_id !== null && $technology->corporation_id !== $corporation->id) {
            throw ValidationException::withMessages([
                'technology_type_id' => $technology->name.' is not on '.$corporation->name.'\'s tree.',
            ]);
        }
    }

    /**
     * @param  array<string, int>  $cost
     */
    private function assertAffordable(Corporation $corporation, array $cost): void
    {
        foreach ($cost as $suitValue => $amount) {
            $suit = ResearchSuit::from($suitValue);
            $held = $corporation->researchPointsIn($suit);

            if ($held < $amount) {
                throw ValidationException::withMessages([
                    'technology_type_id' => sprintf(
                        '%s has %d %s Research Point(s) and needs %d.',
                        $corporation->name,
                        $held,
                        $suit->label(),
                        $amount,
                    ),
                ]);
            }
        }
    }

    /**
     * Refuse a Facility that cannot house this card (rulebook 3.2.2).
     *
     * Four things have to hold: the Facility is the Corporation's, it has
     * finished building, it is of the type the card names if it names one, and
     * it has a slot left. The last of those is the one that reads oddly at
     * first - a Corporation with no Corporate Facility can store nothing at all,
     * which is exactly what "2 multiplied by the number of Corporate Facilities
     * you have" says.
     */
    private function assertCanStore(
        Corporation $corporation,
        TechnologyType $technology,
        Facility $facility,
        ?TechnologyHolding $ignoring = null,
    ): void {
        if ($facility->corporation_id !== $corporation->id) {
            throw ValidationException::withMessages([
                'facility_id' => $facility->name.' is not '.$corporation->name.'\'s Facility.',
            ]);
        }

        $turn = $corporation->game->currentTurn();

        if (! $facility->isAvailableOnTurn($turn?->number)) {
            throw ValidationException::withMessages([
                'facility_id' => $facility->name.' is still being built.',
            ]);
        }

        $required = $technology->requiredFacilityType;

        if ($required !== null && $facility->facility_type_id !== $required->id) {
            throw ValidationException::withMessages([
                'facility_id' => sprintf(
                    '%s has to be housed in a %s Facility.',
                    $technology->name,
                    $required->name,
                ),
            ]);
        }

        $capacity = $this->capacityFor($facility);
        $stored = $this->storedIn($facility);

        if ($ignoring !== null && $ignoring->facility_id === $facility->id && $ignoring->exists) {
            $stored--;
        }

        if ($stored >= $capacity) {
            throw ValidationException::withMessages([
                'facility_id' => $capacity === 0
                    ? $corporation->name.' has no Corporate Facilities, so it can store no technologies.'
                    : sprintf('%s is already storing its %d technologies.', $facility->name, $capacity),
            ]);
        }
    }
}
