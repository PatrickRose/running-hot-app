<?php

namespace App\Services;

use App\Enums\ProtectionKind;
use App\Enums\Tracker;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityTurnState;
use App\Models\FacilityType;
use App\Models\ProtectionCardHolding;
use App\Models\ProtectionCardType;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The ordering and costing rules for Facility defences (rulebook 3.3).
 *
 * These are the fiddly parts the application exists to own: how many slots a
 * Facility has, which end of a stack a new card goes on, and what reordering or
 * removing costs. Every Credit movement goes through TrackerService, so the
 * ledger explains each one.
 *
 * Costs are enforced rather than assumed: a Corporation that cannot afford a
 * reorder is refused. Control overrides by moving Credits with the tracker
 * controls first, exactly as they would for any other rule.
 */
class FacilityDefenceService
{
    /**
     * Slots of each kind every Facility starts with (rulebook 3.3.4).
     */
    public const BASE_SLOTS_PER_KIND = 3;

    public function __construct(private readonly TrackerService $trackers) {}

    /**
     * How many cards of one kind this Corporation may install in any one of its
     * Facilities.
     *
     * Physical and cyber are asked for separately because they do not move
     * together: a Security Facility grants 1 physical slot and 2 cyber ones.
     *
     * Facilities still being built do not count: they are not yours until they
     * open, so a Security Facility requisitioned this turn widens your stacks
     * next turn.
     */
    /**
     * How many cards of one kind may stand in this particular Facility.
     *
     * Null means no limit, which is what a Plot Facility has. Control builds
     * one for the Runners to hit rather than out of a Corporation's economy, so
     * the cap that exists to make Security Facilities worth building has
     * nothing to bite on - a plot building is as deep as the story needs.
     *
     * The one method every caller asks, so that "how many fit here" is answered
     * in one place whoever owns the building.
     */
    public function slotsFor(Facility $facility, ProtectionKind $kind): ?int
    {
        $corporation = $facility->corporation;

        return $corporation === null
            ? null
            : $this->slotsPerKind($corporation, $kind);
    }

    public function slotsPerKind(Corporation $corporation, ProtectionKind $kind): int
    {
        return self::BASE_SLOTS_PER_KIND + $this->grantTotal($corporation, match ($kind) {
            ProtectionKind::Physical => 'physical_slots_granted',
            ProtectionKind::Cyber => 'cyber_slots_granted',
        });
    }

    /**
     * Everything a Corporation's Facilities are worth, in one pass.
     *
     * Asked for as a set because the caller showing one of these numbers
     * usually shows all of them, and every one of them is read off the same
     * count of open Facilities per type.
     *
     * @return array{physical_slots: int, cyber_slots: int, technology_capacity: int, card_move_discount: int}
     */
    public function derivedTotals(Corporation $corporation): array
    {
        $grants = $this->grantTotals($corporation, [
            'physical_slots_granted',
            'cyber_slots_granted',
            'technology_capacity_granted',
            'card_move_discount',
        ]);

        return [
            'physical_slots' => self::BASE_SLOTS_PER_KIND + $grants['physical_slots_granted'],
            'cyber_slots' => self::BASE_SLOTS_PER_KIND + $grants['cyber_slots_granted'],
            'technology_capacity' => $grants['technology_capacity_granted'],
            'card_move_discount' => $grants['card_move_discount'],
        ];
    }

    /**
     * Credits off reordering a stack, from this Corporation's Factories.
     *
     * The discount applies to the reorder rather than to each card moved, so a
     * Corporation with a Factory reorders up to two cards for nothing.
     */
    public function cardMoveDiscount(Corporation $corporation): int
    {
        return $this->grantTotal($corporation, 'card_move_discount');
    }

    /**
     * How many technologies each of this Corporation's Facilities can store.
     *
     * Easy to misread in the rulebook: it scales with the number of Corporate
     * Facilities the Corporation owns, not with the type of the Facility doing
     * the storing. Corporate Facilities grant 2 each, which is where
     * "2 x the number of Corporate Facilities" comes from.
     */
    public function technologyCapacityPerFacility(Corporation $corporation): int
    {
        return $this->grantTotal($corporation, 'technology_capacity_granted');
    }

    /**
     * Install a card at the outermost slot of its own stack (rulebook 3.3.4).
     *
     * Installing costs no Credits. What it costs is a copy of the card: the
     * Corporation hands one out of its holdings, which is what caps how many
     * Facilities a card can defend at once. Buying the copy in the first place
     * happens away from here, at the table with Control.
     *
     * Free of charge is not the same as free of consequence, so this is the one
     * place a copy leaves a Corporation's hand. remove() is the only place one
     * comes back.
     */
    public function install(Facility $facility, ProtectionCardType $cardType): FacilityProtectionCard
    {
        if ($cardType->game_id !== $facility->game_id) {
            throw ValidationException::withMessages([
                'protection_card_type_id' => 'That card belongs to a different game.',
            ]);
        }

        return DB::transaction(function () use ($facility, $cardType): FacilityProtectionCard {
            $installed = $this->stack($facility, $cardType->kind);

            $slots = $this->slotsFor($facility, $cardType->kind);

            if ($slots !== null && $installed->count() >= $slots) {
                throw ValidationException::withMessages([
                    'protection_card_type_id' => sprintf(
                        '%s already holds its %d %s cards.',
                        $facility->name,
                        $slots,
                        $cardType->kind->label(),
                    ),
                ]);
            }

            // One copy of each card title per Facility, across both stacks.
            $duplicate = $facility->protectionCards()
                ->where('protection_card_type_id', $cardType->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'protection_card_type_id' => sprintf(
                        '%s already has a copy of %s installed.',
                        $facility->name,
                        $cardType->name,
                    ),
                ]);
            }

            // A Plot Facility installs out of nothing: there is no Corporation
            // holding copies, and the one-copy-per-Facility economy exists to
            // cap a Corporation rather than Control.
            if (! $facility->isPlotFacility()) {
                $this->takeCopy($facility, $cardType);
            }

            $card = $facility->protectionCards()->create([
                'protection_card_type_id' => $cardType->id,
                'kind' => $cardType->kind,
                // Renumbered immediately below; the outermost slot is position 1.
                'position' => 0,
            ]);

            $this->resequence($facility, $cardType->kind, [
                $card->id,
                ...$installed->pluck('id')->all(),
            ]);

            return $card->refresh();
        });
    }

    /**
     * What a proposed order would cost, without charging for it
     * (rulebook 3.3.4).
     *
     * Security arranges a stack by dragging, so the page has to say what the
     * arrangement will cost before they commit to it. That question is answered
     * here rather than in the browser: the rule is a longest-ascending-run over
     * the old positions, which is exactly the kind of thing that drifts once it
     * is written down twice. reorder() quotes itself from this method, so the
     * number on screen and the number charged cannot disagree.
     *
     * @param  array<int, int>  $orderedCardIds  installed card ids, outermost first
     * @return array{moved: int, discount: int, cost: int, affordable: bool}
     */
    public function quoteReorder(Facility $facility, ProtectionKind $kind, array $orderedCardIds): array
    {
        $current = $this->stack($facility, $kind)->pluck('id')->all();
        $requested = array_values(array_map('intval', $orderedCardIds));

        // A reorder has to name the whole stack, so that a card cannot be
        // dropped out of a Facility without paying the removal cost.
        $requestedSorted = $requested;
        $currentSorted = $current;
        sort($requestedSorted);
        sort($currentSorted);

        if ($requestedSorted !== $currentSorted) {
            throw ValidationException::withMessages([
                'order' => 'That is not the full set of '.$kind->label().' cards in this Facility.',
            ]);
        }

        $moved = $this->moveCost($current, $requested);
        $corporation = $facility->corporation;

        // Nobody to bill for a Plot Facility, so nothing is charged. The count
        // of cards that move is still reported, because that is a fact about
        // the arrangement - but no discount is claimed for it, since a discount
        // is a Factory's doing and there is no Factory here.
        if ($corporation === null) {
            return [
                'moved' => $moved,
                'discount' => 0,
                'cost' => 0,
                'affordable' => true,
            ];
        }

        $discount = min($moved, $this->cardMoveDiscount($corporation));
        $cost = $moved - $discount;

        return [
            'moved' => $moved,
            'discount' => $discount,
            'cost' => $cost,
            'affordable' => $corporation->credits >= $cost,
        ];
    }

    /**
     * Reorder one stack, charging 1 Credit for each card that had to move
     * (rulebook 3.3.4).
     *
     * @param  array<int, int>  $orderedCardIds  installed card ids, outermost first
     * @return int Credits charged
     */
    public function reorder(
        Facility $facility,
        ProtectionKind $kind,
        array $orderedCardIds,
        ?User $actor = null,
    ): int {
        return DB::transaction(function () use ($facility, $kind, $orderedCardIds, $actor): int {
            $quote = $this->quoteReorder($facility, $kind, $orderedCardIds);

            if ($quote['cost'] > 0) {
                $this->charge(
                    $facility,
                    $quote['cost'],
                    sprintf(
                        'Moved %d %s card(s) in %s',
                        $quote['moved'],
                        $kind->label(),
                        $facility->name,
                    ),
                    $actor,
                );
            }

            $this->resequence($facility, $kind, array_values(array_map('intval', $orderedCardIds)));

            return $quote['cost'];
        });
    }

    /**
     * Remove an installed card. The first removal from a Facility each turn is
     * free; every subsequent one costs 1 Credit (rulebook 3.3.4).
     *
     * @return int Credits charged
     */
    public function remove(FacilityProtectionCard $card, ?User $actor = null): int
    {
        return DB::transaction(function () use ($card, $actor): int {
            $facility = $card->facility;
            $turn = $facility->game->currentTurn();

            // Before the clock starts there is no turn to count removals
            // against, so Control setting up a roster is not charged for it.
            // A Plot Facility is free to take apart as well as to fill: the
            // removal cost is Credits off the Corporation, and there is none.
            $state = $turn === null ? null : $facility->stateForTurn($turn);
            $cost = $state !== null
                && $state->cards_removed > 0
                && ! $facility->isPlotFacility() ? 1 : 0;

            if ($cost > 0) {
                $this->charge(
                    $facility,
                    $cost,
                    sprintf('Removed %s from %s', $card->cardType->name, $facility->name),
                    $actor,
                );
            }

            $state?->increment('cards_removed');

            $kind = $card->kind;
            $cardTypeId = $card->protection_card_type_id;
            $card->delete();

            $corporation = $facility->corporation;

            if ($corporation !== null) {
                $this->returnCopy($corporation, $cardTypeId);
            }

            $this->resequence($facility, $kind, $this->stack($facility, $kind)->pluck('id')->all());

            return $cost;
        });
    }

    /**
     * Set the budget placed on a Facility for this turn (rulebook 3.3.5).
     *
     * The budget is escrowed: raising it moves Credits out of the Corporation
     * now, and whatever is left returns at the end of the Action phase. That is
     * what placing physical Credits on the Facility does at the table, and it
     * stops the same Credits being promised to two Facilities.
     */
    public function setSecurityBudget(
        Facility $facility,
        int $budget,
        Turn $turn,
        ?User $actor = null,
    ): FacilityTurnState {
        return DB::transaction(function () use ($facility, $budget, $turn, $actor): FacilityTurnState {
            $state = $facility->stateForTurn($turn);

            if ($budget < $state->security_budget_spent) {
                throw ValidationException::withMessages([
                    'security_budget' => sprintf(
                        '%d Credits of this budget have already been spent.',
                        $state->security_budget_spent,
                    ),
                ]);
            }

            $delta = $budget - $state->security_budget;
            $corporation = $facility->corporation;

            // A Plot Facility's budget is escrowed from nowhere. There is no
            // Corporation whose Credits could be promised twice, so the number
            // Control writes is simply what the building has to spend - and
            // nothing goes in the ledger, because no tracker moved.
            if ($corporation === null) {
                $delta = 0;
            }

            if ($delta > 0) {
                $this->charge(
                    $facility,
                    $delta,
                    sprintf('Security budget for %s', $facility->name),
                    $actor,
                );
            } elseif ($delta < 0) {
                $this->trackers->adjust(
                    $corporation,
                    Tracker::CorporationCredits,
                    -$delta,
                    sprintf('Security budget for %s reduced', $facility->name),
                    $actor,
                );
            }

            $state->forceFill([
                'security_budget' => $budget,
                'budget_returned_at' => null,
            ])->save();

            return $state;
        });
    }

    /**
     * Hand back every unspent security budget (rulebook 3.3.5).
     *
     * Sweeps this turn and any earlier one still holding an escrow, rather than
     * only the turn given: a budget placed after its own Action phase had
     * already closed would otherwise sit on the Facility forever, and Credits
     * the application is holding must always find their way home.
     *
     * @return array<string, int> Credits returned, keyed by Facility name
     */
    public function returnUnspentBudgets(Turn $turn, ?User $actor = null): array
    {
        $turnIds = Turn::query()
            ->where('game_id', $turn->game_id)
            ->where('number', '<=', $turn->number)
            ->pluck('id');

        $states = FacilityTurnState::query()
            ->whereIn('turn_id', $turnIds)
            ->where('security_budget', '>', 0)
            ->whereNull('budget_returned_at')
            ->with('facility.corporation', 'turn')
            ->get();

        $returned = [];

        foreach ($states as $state) {
            $unspent = $state->unspentBudget();
            $corporation = $state->facility->corporation;

            // A Plot Facility's budget was never taken off anybody, so there is
            // nothing to hand back - only the mark that says this turn's escrow
            // has been dealt with, which keeps the sweep from looking at it
            // again every phase for the rest of the game.
            if ($unspent > 0 && $corporation !== null) {
                $this->trackers->adjust(
                    $corporation,
                    Tracker::CorporationCredits,
                    $unspent,
                    sprintf(
                        'Turn %d unspent security budget for %s',
                        $state->turn->number,
                        $state->facility->name,
                    ),
                    $actor,
                    automated: true,
                );

                $returned[$state->facility->name] = $unspent;
            }

            $state->forceFill(['budget_returned_at' => now()])->save();
        }

        return $returned;
    }

    /**
     * How many cards have to move to get from one order to another.
     *
     * Cards that keep their relative order can stay put, so the cost is the
     * number left over once the longest such run is kept: the worked example in
     * rulebook 3.3.4 is A, B, C to B, C, A costing 1 Credit (only A moves), and
     * A, B, C to C, B, A costing 2 (A and C both move around B).
     *
     * @param  array<int, int>  $before
     * @param  array<int, int>  $after
     */
    public function moveCost(array $before, array $after): int
    {
        $index = array_flip(array_values($before));

        // Where each card sat before, read in the order they will sit now. The
        // longest ascending run through that is the set that can stay put.
        $positions = [];

        foreach (array_values($after) as $id) {
            if (! isset($index[$id])) {
                throw new InvalidArgumentException(
                    'The new order names a card that is not in the old one.'
                );
            }

            $positions[] = $index[$id];
        }

        return count($positions) - $this->longestAscendingRun($positions);
    }

    /**
     * The stack of one kind, outermost card first.
     *
     * @return Collection<int, FacilityProtectionCard>
     */
    public function stack(Facility $facility, ProtectionKind $kind): Collection
    {
        return $facility->protectionCards()
            ->where('kind', $kind)
            ->with('cardType')
            ->orderBy('position')
            ->get();
    }

    /**
     * Write positions 1..n over a stack, so it stays dense and in the given
     * encounter order.
     *
     * @param  array<int, int>  $orderedCardIds
     */
    protected function resequence(Facility $facility, ProtectionKind $kind, array $orderedCardIds): void
    {
        foreach (array_values($orderedCardIds) as $offset => $cardId) {
            $facility->protectionCards()
                ->whereKey($cardId)
                ->where('kind', $kind)
                ->update(['position' => $offset + 1]);
        }
    }

    /**
     * How many uninstalled copies of a card a Corporation has left.
     */
    public function copiesInHand(Corporation $corporation, ProtectionCardType $cardType): int
    {
        return (int) ($corporation->protectionCardHoldings()
            ->where('protection_card_type_id', $cardType->id)
            ->value('copies') ?? 0);
    }

    /**
     * Set how many copies of a card a Corporation holds.
     *
     * Control's, and only Control's. The shop, the auctions and the research
     * grants that would move this number all happen at the table, so the
     * application does not try to model them - it holds the number Control
     * writes down. Installed copies are untouched: this is the hand, not the
     * total.
     */
    public function setCopiesInHand(
        Corporation $corporation,
        ProtectionCardType $cardType,
        int $copies,
    ): ProtectionCardHolding {
        if ($copies < 0) {
            throw ValidationException::withMessages([
                'copies' => 'A Corporation cannot hold fewer than no copies of a card.',
            ]);
        }

        /** @var ProtectionCardHolding $holding */
        $holding = $corporation->protectionCardHoldings()->updateOrCreate(
            ['protection_card_type_id' => $cardType->id],
            ['copies' => $copies],
        );

        return $holding;
    }

    /**
     * Put one more copy into the Corporation's hand.
     *
     * The shop's way in (rulebook 3.3.3), and the counterpart of
     * EquipmentService::giveCopy. Setting the count outright is Control saying
     * where it ended up; this is one copy arriving, which is what a purchase
     * is - so a sale cannot silently overwrite a count that moved underneath
     * it while the Security player was reading the price.
     *
     * The row is created where there was none: a Corporation buying its first
     * Angel has never held one.
     */
    public function giveCopy(Corporation $corporation, ProtectionCardType $cardType): void
    {
        $this->giveCopies($corporation, $cardType, 1);
    }

    /**
     * Hand several copies over at once.
     *
     * Adding rather than setting, which is the difference between this and
     * setCopiesInHand and the reason both exist: Control handing cards over
     * knows what it is giving and not what the Corporation already holds, so a
     * give that set the count would quietly take away the four Angels it had.
     * EquipmentService::giveCopies is the same method one table along.
     */
    public function giveCopies(
        Corporation $corporation,
        ProtectionCardType $cardType,
        int $copies = 1,
    ): ProtectionCardHolding {
        if ($copies < 1) {
            throw ValidationException::withMessages([
                'copies' => 'Giving a Corporation no copies of a card is not giving it anything.',
            ]);
        }

        /** @var ProtectionCardHolding $holding */
        $holding = $corporation->protectionCardHoldings()->firstOrCreate(
            ['protection_card_type_id' => $cardType->id],
            ['copies' => 0],
        );

        $holding->increment('copies', $copies);

        return $holding->refresh();
    }

    /**
     * Take one copy back out of the Corporation's hand, refusing if it has
     * none there to take.
     *
     * Installing has its own version of this below, which knows the Facility
     * the card is going into and says so when it refuses. This one is for
     * everything that takes a copy away without installing it - the shop
     * unwinding a sale it should not have made - so the caller supplies the
     * sentence, because "left to install" would be the wrong thing to tell
     * somebody who was not installing anything.
     *
     * A copy already standing in a Facility is not in the hand and will not be
     * found here. That refusal is the right one: the card exists, it is simply
     * not the Corporation's to hand back until it comes off the stack.
     */
    public function takeCopyFromHand(
        Corporation $corporation,
        ProtectionCardType $cardType,
        string $message,
    ): void {
        $taken = $corporation->protectionCardHoldings()
            ->where('protection_card_type_id', $cardType->id)
            ->where('copies', '>', 0)
            ->decrement('copies');

        if ($taken === 0) {
            throw ValidationException::withMessages(['copies' => $message]);
        }
    }

    /**
     * Take one copy out of the Corporation's hand, refusing if it has none.
     *
     * A Corporation with no row for a card holds none of it, which is the same
     * as holding zero: it was never in the briefing and nobody has bought one.
     * Control raises the count first, exactly as they would move Credits before
     * a purchase the rules would otherwise refuse.
     */
    protected function takeCopy(Facility $facility, ProtectionCardType $cardType): void
    {
        $corporation = $facility->corporation;

        $taken = $corporation->protectionCardHoldings()
            ->where('protection_card_type_id', $cardType->id)
            ->where('copies', '>', 0)
            ->decrement('copies');

        if ($taken === 0) {
            throw ValidationException::withMessages([
                'protection_card_type_id' => sprintf(
                    '%s has no copies of %s left to install.',
                    $corporation->name,
                    $cardType->name,
                ),
            ]);
        }
    }

    /**
     * Put a copy back into the Corporation's hand.
     *
     * A card that came off a Facility is a card the Corporation has again, so
     * this creates the row where there was none - a card Control installed
     * without the Corporation ever holding one still returns somewhere.
     */
    protected function returnCopy(Corporation $corporation, int $cardTypeId): void
    {
        $holding = $corporation->protectionCardHoldings()->firstOrCreate(
            ['protection_card_type_id' => $cardTypeId],
            ['copies' => 0],
        );

        $holding->increment('copies');
    }

    /**
     * Take Credits off the Facility's Corporation, refusing if it cannot pay.
     *
     * A Plot Facility has no Corporation, and nothing it does costs Credits:
     * every caller here already quotes nought for one, so this is the floor
     * under that rather than a case reached in normal play.
     */
    protected function charge(Facility $facility, int $amount, string $reason, ?User $actor): void
    {
        $corporation = $facility->corporation;

        if ($corporation === null) {
            return;
        }

        if ($corporation->credits < $amount) {
            throw ValidationException::withMessages([
                'credits' => sprintf(
                    '%s cannot afford the %d Credit(s).',
                    $corporation->name,
                    $amount,
                ),
            ]);
        }

        $this->trackers->adjust(
            $corporation,
            Tracker::CorporationCredits,
            -$amount,
            $reason,
            $actor,
        );
    }

    /**
     * Total of one grant column across the Corporation's open Facilities.
     */
    protected function grantTotal(Corporation $corporation, string $column): int
    {
        return $this->grantTotals($corporation, [$column])[$column];
    }

    /**
     * Totals of several grant columns from one read of the Facility list.
     *
     * Counted per type rather than summed in SQL, because a type that steps at
     * 2, 3, 5, 8 is not worth its base effect times the number you own.
     *
     * @param  array<int, string>  $columns
     * @return array<string, int>
     */
    protected function grantTotals(Corporation $corporation, array $columns): array
    {
        $totals = array_fill_keys($columns, 0);
        $counts = $this->openTypeCounts($corporation);

        if ($counts === []) {
            return $totals;
        }

        $types = FacilityType::query()->whereKey(array_keys($counts))->get();

        foreach ($types as $type) {
            foreach ($columns as $column) {
                $totals[$column] += $type->grantTotal($column, $counts[$type->id] ?? 0);
            }
        }

        return $totals;
    }

    /**
     * How many open Facilities of each type this Corporation owns.
     *
     * @return array<int, int> counts keyed by facility type id
     */
    protected function openTypeCounts(Corporation $corporation): array
    {
        $turn = $corporation->game->currentTurn();
        $turnNumber = $turn === null ? Facility::FIRST_TURN : $turn->number;

        /** @var array<int, int> $counts */
        $counts = $corporation->facilities()
            ->availableOnTurn($turnNumber)
            ->selectRaw('facility_type_id, count(*) as total')
            ->groupBy('facility_type_id')
            ->pluck('total', 'facility_type_id')
            ->all();

        return $counts;
    }

    /**
     * @param  array<int, int>  $positions
     */
    protected function longestAscendingRun(array $positions): int
    {
        $best = 0;
        $lengths = [];

        foreach ($positions as $outer => $position) {
            $lengths[$outer] = 1;

            foreach (array_slice($positions, 0, $outer, true) as $inner => $earlier) {
                if ($earlier < $position) {
                    $lengths[$outer] = max($lengths[$outer], ($lengths[$inner] ?? 0) + 1);
                }
            }

            $best = max($best, $lengths[$outer]);
        }

        return $best;
    }
}
