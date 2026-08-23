<?php

namespace App\Services;

use App\Enums\ProtectionKind;
use App\Enums\Tracker;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityTurnState;
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
     * How many cards of each kind this Corporation may install in any one of
     * its Facilities.
     *
     * Facilities still being built do not count: they are not yours until they
     * open, so a Security Facility requisitioned this turn widens your stacks
     * next turn.
     */
    public function slotsPerKind(Corporation $corporation): int
    {
        return self::BASE_SLOTS_PER_KIND + $this->grantTotal($corporation, 'protection_slots_granted');
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
     * Installing is free. What it costs is the card itself, bought from the
     * Corporation shop or granted by research, which happens away from here.
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

            $slots = $this->slotsPerKind($facility->corporation);

            if ($installed->count() >= $slots) {
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

            $cost = $this->moveCost($current, $requested);

            if ($cost > 0) {
                $this->charge(
                    $facility,
                    $cost,
                    sprintf(
                        'Reordered %d %s card(s) in %s',
                        $cost,
                        $kind->label(),
                        $facility->name,
                    ),
                    $actor,
                );
            }

            $this->resequence($facility, $kind, $requested);

            return $cost;
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
            $state = $turn === null ? null : $facility->stateForTurn($turn);
            $cost = $state !== null && $state->cards_removed > 0 ? 1 : 0;

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
            $card->delete();

            $this->resequence($facility, $kind, $this->stack($facility, $kind)->pluck('id')->all());

            return $cost;
        });
    }

    /**
     * Place (or lift) Security's meeple at a Facility for this turn
     * (rulebook 3.3.5).
     *
     * One meeple, so directing at a Facility lifts it from wherever it was.
     */
    public function directSecurity(Facility $facility, bool $directed, Turn $turn): FacilityTurnState
    {
        return DB::transaction(function () use ($facility, $directed, $turn): FacilityTurnState {
            if ($directed) {
                $siblings = Facility::query()
                    ->where('corporation_id', $facility->corporation_id)
                    ->pluck('id');

                FacilityTurnState::query()
                    ->where('turn_id', $turn->id)
                    ->whereIn('facility_id', $siblings)
                    ->update(['security_directed' => false]);
            }

            $state = $facility->stateForTurn($turn);
            $state->forceFill(['security_directed' => $directed])->save();

            return $state;
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

            if ($delta > 0) {
                $this->charge(
                    $facility,
                    $delta,
                    sprintf('Security budget for %s', $facility->name),
                    $actor,
                );
            } elseif ($delta < 0) {
                $this->trackers->adjust(
                    $facility->corporation,
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

            if ($unspent > 0) {
                $this->trackers->adjust(
                    $state->facility->corporation,
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
     * Take Credits off the Facility's Corporation, refusing if it cannot pay.
     */
    protected function charge(Facility $facility, int $amount, string $reason, ?User $actor): void
    {
        $corporation = $facility->corporation;

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
        $turn = $corporation->game->currentTurn();
        $turnNumber = $turn === null ? Facility::FIRST_TURN : $turn->number;

        return (int) $corporation->facilities()
            ->availableOnTurn($turnNumber)
            ->join('facility_types', 'facility_types.id', '=', 'facilities.facility_type_id')
            ->sum('facility_types.'.$column);
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
