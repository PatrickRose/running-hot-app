<?php

namespace App\Http\Controllers;

use App\Actions\RequisitionFacility;
use App\Http\Requests\RequisitionFacilityRequest;
use App\Models\Corporation;
use App\Models\FacilityType;
use Illuminate\Http\RedirectResponse;

/**
 * A CEO building their own Corporation a Facility (rulebook 3.3.1).
 *
 * CEOs are the only ones allowed to build Facilities, and they no longer have
 * to find an organiser to press the button for them: this route is the whole
 * of it, and the Credits come off the Corporation as it is built.
 *
 * Thin, like every player-facing route here: the Setup-phase rule, the price,
 * the purse and the ledger are all RequisitionFacility's, which is also what
 * Control's panel calls. What this route does not offer is what only Control
 * may do - a price other than the type sheet's, or a build that opens at once.
 */
class FacilityRequisitionController extends Controller
{
    public function __construct(private readonly RequisitionFacility $requisition) {}

    public function store(Corporation $corporation, RequisitionFacilityRequest $request): RedirectResponse
    {
        /** @var FacilityType $type */
        $type = $corporation->game->facilityTypes()->findOrFail($request->integer('facility_type_id'));

        $facility = $this->requisition->handle(
            $corporation,
            $type,
            $request->string('name')->trim()->toString(),
            $type->build_cost,
            $request->user(),
        );

        return back()->with('status', sprintf(
            '%s requisitioned for %d Credits. It opens on turn %d.',
            $facility->name,
            $type->build_cost,
            $facility->available_from_turn,
        ));
    }
}
