<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\StoreFacilityTypeRequest;
use App\Models\FacilityType;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * The game's Facility type catalogue (rulebook 3.3.1).
 *
 * A game starts with Research, Security and Corporate, but the rulebook says
 * more types may be researched during the game, so Control adds them here.
 */
class FacilityTypeController extends Controller
{
    public function store(Game $game, StoreFacilityTypeRequest $request): RedirectResponse
    {
        $attributes = $request->typeAttributes();

        // The slug is derived, so two names that slug the same would collide on
        // a key that the name rule cannot see.
        $keyTaken = $game->facilityTypes()->where('key', $attributes['key'])->exists();

        if ($keyTaken) {
            throw ValidationException::withMessages([
                'name' => 'That is too close to an existing Facility type name.',
            ]);
        }

        $type = $game->facilityTypes()->create($attributes);

        return back()->with('status', $type->name.' added as a Facility type.');
    }

    public function update(
        Game $game,
        FacilityType $facilityType,
        StoreFacilityTypeRequest $request,
    ): RedirectResponse {
        abort_if($facilityType->game_id !== $game->id, 404);

        $facilityType->update($request->validated());

        return back()->with('status', $facilityType->name.' updated.');
    }

    /**
     * Types that Facilities are still built as are kept: deleting one would
     * leave those Facilities with no type at all, and Control should retype
     * them first.
     */
    public function destroy(Game $game, FacilityType $facilityType): RedirectResponse
    {
        abort_if($facilityType->game_id !== $game->id, 404);

        if ($facilityType->facilities()->exists()) {
            throw ValidationException::withMessages([
                'facility_type_id' => sprintf(
                    'Facilities are still built as %s. Retype them first.',
                    $facilityType->name,
                ),
            ]);
        }

        $facilityType->delete();

        return back()->with('status', $facilityType->name.' removed from the catalogue.');
    }
}
