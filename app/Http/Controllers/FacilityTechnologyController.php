<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\TechnologyHolding;
use App\Policies\FacilityPolicy;
use App\Services\TechnologyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Moving a technology card between a Corporation's own Facilities
 * (rulebook 3.2.2).
 *
 * The rulebook has a Research player place a technology when they research it
 * and then says nothing about moving it afterwards, which is the table's answer
 * rather than the absence of one: the cards are in front of you and you pick
 * one up. What the application had instead was a row nobody could move without
 * Control writing a PATCH by hand, and a Corporation that lost a Facility, or
 * wanted four pieces of Power in four buildings rather than one, had no way to
 * say so.
 *
 * It is Security's, for the reason the stacks are. Which Facility holds a
 * technology is a decision about what a Run would come away with and what is
 * worth defending - the same decision the stacks on that page are, made by the
 * same person, and 3.4.2 already keeps a Facility's contents Secret from
 * everyone outside the Corporation rather than from Security. The CEO and the
 * Research player still read the cards where they are stored and still cannot
 * move them: a board three people can drag at once is a board nobody can trust.
 *
 * Control reaches this through {@see FacilityPolicy::before()},
 * and keeps its own panel besides - a ruling mid-game must not wait on the
 * Security player being at their laptop.
 *
 * Nothing about the rules lives here. Storage is still 2 per Corporate Facility,
 * a technology that names a Facility type still has to be housed in one, and a
 * Facility still being built still holds nothing - all of it in
 * {@see TechnologyService::place()}, which is the same method Control's route
 * goes through.
 */
class FacilityTechnologyController extends Controller
{
    public function __construct(private readonly TechnologyService $technologies) {}

    /**
     * Store a card the Corporation already holds in this Facility instead.
     *
     * The destination is the route parameter rather than a field, because it is
     * what the gesture names: the card was dropped *on* a Facility. That also
     * makes it the thing authorised - "may you defend this Facility" is asked of
     * the Facility the card is going into.
     *
     * The card having to be the same Corporation's is the second half of that
     * boundary and this controller's own business: the policy only asks whether
     * the Facility is yours, so without it a Security player could pull a
     * rival's technology across into their own building.
     */
    public function move(Facility $facility, TechnologyHolding $holding): RedirectResponse
    {
        Gate::authorize('defend', $facility);

        abort_if($holding->game_id !== $facility->game_id, 404);
        abort_if($holding->corporation_id !== $facility->corporation_id, 404);

        // Dropped back where it came from. Nothing moved, so nothing is said:
        // a status toast for a card that did not go anywhere reads as a change.
        if ($holding->facility_id === $facility->id) {
            return back();
        }

        $from = $holding->facility?->name;

        $this->technologies->place($holding, $facility);

        return back()->with('status', sprintf(
            '%s moved %sto %s.',
            $holding->technologyType->name,
            $from === null ? '' : 'from '.$from.' ',
            $facility->name,
        ));
    }
}
