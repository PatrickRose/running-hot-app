<?php

namespace App\Actions;

use App\Enums\PhaseType;
use App\Enums\Tracker;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\User;
use App\Services\TrackerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Build a Facility (rulebook 3.3.1).
 *
 * During the Setup Phase, Security takes a requisition slip to their CEO, who
 * signs off and provides the Credits. Facility Control starts the build and the
 * Facility becomes available during the next Setup Phase - so what this records
 * is the turn it opens, not a flag somebody has to flip later.
 *
 * The build cost is passed in rather than derived. The rulebook says the CEO
 * provides "the required Credits" without saying what they are, so it is
 * Control's number to set, and a game that gives Facilities away sets zero.
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
        if ($facilityType->game_id !== $corporation->game_id) {
            throw ValidationException::withMessages([
                'facility_type_id' => 'That Facility type belongs to a different game.',
            ]);
        }

        $game = $corporation->game;
        $turn = $game->currentTurn();
        $currentTurn = $turn === null ? Facility::FIRST_TURN : $turn->number;

        if (! $immediate && $game->currentPhase()?->type !== PhaseType::Setup) {
            throw ValidationException::withMessages([
                'facility_type_id' => 'Facilities may only be requisitioned during a Setup phase.',
            ]);
        }

        // Available during the next Setup phase, which is the next turn.
        $availableFrom = $immediate ? $currentTurn : $currentTurn + 1;

        if ($cost > 0 && $corporation->credits < $cost) {
            throw ValidationException::withMessages([
                'cost' => sprintf('%s cannot afford the %d Credit build.', $corporation->name, $cost),
            ]);
        }

        return DB::transaction(function () use (
            $corporation,
            $facilityType,
            $name,
            $cost,
            $actor,
            $availableFrom,
        ): Facility {
            /** @var Facility $facility */
            $facility = $corporation->facilities()->create([
                'game_id' => $corporation->game_id,
                'facility_type_id' => $facilityType->id,
                'name' => $name,
                'available_from_turn' => $availableFrom,
            ]);

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
}
