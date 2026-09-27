<?php

namespace App\Actions;

use App\Enums\PhaseType;
use App\Enums\Tracker;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\User;
use App\Services\TrackerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Build a Facility (rulebook 3.3.1).
 *
 * During the Setup Phase a CEO - the only seat allowed to build Facilities -
 * spends their Corporation's Credits on one, and it becomes available during
 * the next Setup Phase. So what this records is the turn it opens, not a flag
 * somebody has to flip later.
 *
 * The build cost is passed in rather than derived here, because two callers
 * name it differently. A CEO requisitioning from /facilities pays the type
 * sheet's price and nothing else; Control names any price it likes, nought
 * included, because MCM's Construction Leader is a discount on exactly this and
 * a game that gives Facilities away sets zero.
 *
 * A Plot Facility goes through {@see buildForControl()} instead, and it is a
 * separate method rather than a null Corporation on this one because none of
 * what makes this a requisition applies to it: no CEO builds it and nobody
 * pays for it.
 */
class RequisitionFacility
{
    public function __construct(private readonly TrackerService $trackers) {}

    /**
     * @param  bool  $immediate  Control override: open the Facility now rather
     *                           than next turn, and skip the phase check. This
     *                           is how a game's starting Facilities are put in.
     * @param  string|null  $reason  What the ledger says the Credits went on,
     *                               when it was not the Corporation's own
     *                               requisition.
     */
    public function handle(
        Corporation $corporation,
        FacilityType $facilityType,
        string $name,
        int $cost = 0,
        ?User $actor = null,
        bool $immediate = false,
        ?string $reason = null,
    ): Facility {
        $game = $corporation->game;

        $this->assertTypeBelongsTo($facilityType, $game);

        $turn = $game->currentTurn();
        $currentTurn = $turn === null ? Facility::FIRST_TURN : $turn->number;

        if (! $immediate && $game->currentPhase()?->type !== PhaseType::Setup) {
            throw ValidationException::withMessages([
                'facility_type_id' => 'Facilities may only be requisitioned during a Setup phase.',
            ]);
        }

        // Available during the next Setup phase, which is the next turn.
        $availableFrom = $immediate ? $currentTurn : $currentTurn + 1;

        // By hand rather than left to the unique key, so that a clash reads as
        // a refusal against the name box instead of a database error - and
        // here rather than in a controller, because a CEO and Control both
        // reach it and neither route should carry its own copy of the rule.
        if ($corporation->facilities()->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => $corporation->name.' already has a Facility called that.',
            ]);
        }

        if ($cost > 0 && $corporation->credits < $cost) {
            throw ValidationException::withMessages([
                'cost' => sprintf('%s cannot afford the %d Credit build.', $corporation->name, $cost),
            ]);
        }

        return DB::transaction(function () use (
            $game,
            $corporation,
            $facilityType,
            $name,
            $cost,
            $actor,
            $availableFrom,
            $reason,
        ): Facility {
            $facility = $this->create($game, $corporation, $facilityType, $name, $availableFrom);

            if ($cost > 0) {
                $this->trackers->adjust(
                    $corporation,
                    Tracker::CorporationCredits,
                    -$cost,
                    $reason ?? sprintf('Requisitioned %s (%s)', $name, $facilityType->name),
                    $actor,
                );
            }

            return $facility;
        });
    }

    /**
     * One Corporation building a Facility for another.
     *
     * MCM's Construction Leader lets it build for other Corporations, and the
     * rulebook prices none of it: what the owner is charged and what the
     * builder is paid are both Control's to name, so neither is derived here.
     * The two are independent rather than one paying the other - the owner's
     * charge goes where every build cost goes, and the builder's fee is a
     * payment on top of it. The Facility is the owner's in every respect; the
     * builder only appears in the ledger.
     */
    public function buildOnBehalf(
        Corporation $builder,
        Corporation $owner,
        FacilityType $facilityType,
        string $name,
        int $charge,
        int $fee,
        ?User $actor = null,
        bool $immediate = false,
    ): Facility {
        if ($builder->is($owner)) {
            throw ValidationException::withMessages([
                'builder_corporation_id' => sprintf('%s would be building for itself - use Build a Facility instead.', $owner->name),
            ]);
        }

        if ($builder->game_id !== $owner->game_id) {
            throw ValidationException::withMessages([
                'builder_corporation_id' => sprintf('%s is in a different game.', $builder->name),
            ]);
        }

        return DB::transaction(function () use ($builder, $owner, $facilityType, $name, $charge, $fee, $actor, $immediate): Facility {
            $facility = $this->handle(
                $owner,
                $facilityType,
                $name,
                $charge,
                $actor,
                $immediate,
                sprintf('%s built %s (%s)', $builder->name, $name, $facilityType->name),
            );

            if ($fee > 0) {
                $this->trackers->adjust(
                    $builder,
                    Tracker::CorporationCredits,
                    $fee,
                    sprintf('Built %s (%s) for %s', $name, $facilityType->name, $owner->name),
                    $actor,
                );
            }

            return $facility;
        });
    }

    /**
     * Build a Plot Facility: Control's own building, for the Runners to hit.
     *
     * It opens at once and costs nothing, which is the whole difference from a
     * requisition. The Setup-phase rule of 3.3.1 is about a Corporation
     * spending its turn's Credits on a build, and there is no Corporation and
     * no Credits here - a plot target appears when the story needs one rather
     * than when the clock next comes round. Nothing goes through
     * TrackerService, because no tracker moved.
     */
    public function buildForControl(
        Game $game,
        FacilityType $facilityType,
        string $name,
    ): Facility {
        $this->assertTypeBelongsTo($facilityType, $game);

        $turn = $game->currentTurn();

        return $this->create(
            $game,
            null,
            $facilityType,
            $name,
            $turn === null ? Facility::FIRST_TURN : $turn->number,
        );
    }

    private function assertTypeBelongsTo(FacilityType $facilityType, Game $game): void
    {
        if ($facilityType->game_id !== $game->id) {
            throw ValidationException::withMessages([
                'facility_type_id' => 'That Facility type belongs to a different game.',
            ]);
        }
    }

    private function create(
        Game $game,
        ?Corporation $corporation,
        FacilityType $facilityType,
        string $name,
        int $availableFrom,
    ): Facility {
        /** @var Facility $facility */
        $facility = Facility::query()->create([
            'game_id' => $game->id,
            'corporation_id' => $corporation?->id,
            'facility_type_id' => $facilityType->id,
            'name' => $name,
            'available_from_turn' => $availableFrom,
        ]);

        return $facility;
    }
}
