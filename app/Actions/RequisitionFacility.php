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
     */
    public function handle(
        Corporation $corporation,
        FacilityType $facilityType,
        string $name,
        int $cost = 0,
        ?User $actor = null,
        bool $immediate = false,
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
        ): Facility {
            $facility = $this->create($game, $corporation, $facilityType, $name, $availableFrom);

            if ($cost > 0) {
                $this->trackers->adjust(
                    $corporation,
                    Tracker::CorporationCredits,
                    -$cost,
                    sprintf('Requisitioned %s (%s)', $name, $facilityType->name),
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
