<?php

namespace App\Http\Controllers;

use App\Actions\RequisitionFacility;
use App\Http\Requests\RequisitionFacilityRequest;
use App\Models\Corporation;
use App\Models\FacilityType;
use App\Support\BuildableFacilityTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * A CEO building their own Corporation a Facility (rulebook 3.3.1).
 *
 * CEOs are the only ones allowed to build Facilities, and they no longer have
 * to find an organiser to press the button for them: this route is the whole
 * of it, and the Credits come off the Corporation as it is built.
 *
 * Thin, like every player-facing route here: the Setup-phase rule, the price,
 * the purse and the ledger are all RequisitionFacility's, and the price is
 * the Corporation's own (`Corporation::facilityBuildCost()`), which is also what
 * Control's panel calls. What this route does not offer is what only Control
 * may do - a price other than the type sheet's, or a build that opens at once.
 */
class FacilityRequisitionController extends Controller
{
    public function __construct(
        private readonly RequisitionFacility $requisition,
        private readonly BuildableFacilityTypes $buildable,
    ) {}

    public function store(Corporation $corporation, RequisitionFacilityRequest $request): RedirectResponse
    {
        /** @var FacilityType $type */
        $type = $corporation->game->facilityTypes()->findOrFail($request->integer('facility_type_id'));

        // Refused by name rather than only left off the page: a type the
        // Corporation has not unlocked is one it may not build, whoever typed
        // the id in. Control is not asked, because Control builds anything
        // from its own panel.
        if (! $this->buildable->allows($corporation, $type)) {
            throw ValidationException::withMessages([
                'facility_type_id' => sprintf('%s has not unlocked %s Facilities.', $corporation->name, $type->name),
            ]);
        }

        // The sheet's price less any build discount the Corporation holds -
        // MCM's Construction Leader, and nobody else's.
        $cost = $corporation->facilityBuildCost($type);

        $facility = $this->requisition->handle(
            $corporation,
            $type,
            $request->string('name')->trim()->toString(),
            $cost,
            $request->user(),
        );

        return back()->with('status', sprintf(
            '%s requisitioned for %d Credits. It opens on turn %d.',
            $facility->name,
            $cost,
            $facility->available_from_turn,
        ));
    }
}
