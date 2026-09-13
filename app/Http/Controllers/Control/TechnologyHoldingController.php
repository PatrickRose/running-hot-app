<?php

namespace App\Http\Controllers\Control;

use App\Enums\TechnologyHoldingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\GrantTechnologyRequest;
use App\Http\Requests\Control\UpdateTechnologyHoldingRequest;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Services\TechnologyService;
use Illuminate\Http\RedirectResponse;

/**
 * The technology cards Corporations hold (rulebook 3.2.5, 3.2.6).
 *
 * Control's, because every way a card arrives other than being researched goes
 * through Research Control at the table: a shared copy is made "by going to
 * Research Control with the technology in question", and a copy, a theft or a
 * destruction is the outcome of a Run that Control has just adjudicated.
 *
 * What a card that arrived this way is worth off the research cost defaults to
 * its kind - 25% for a weak copy, 50% for a good one or a theft - and can be
 * named outright, because 3.2.6 leaves it to "the strength of the copy" as
 * Research Control judges it.
 */
class TechnologyHoldingController extends Controller
{
    public function __construct(private readonly TechnologyService $technologies) {}

    /**
     * Put a card into a Corporation's hands.
     */
    public function store(Game $game, GrantTechnologyRequest $request): RedirectResponse
    {
        /** @var Corporation $corporation */
        $corporation = $game->corporations()->findOrFail($request->integer('corporation_id'));

        /** @var TechnologyType $technology */
        $technology = $game->technologyTypes()->findOrFail($request->integer('technology_type_id'));

        $facilityId = $request->integer('facility_id');

        /** @var Facility|null $facility */
        $facility = $facilityId === 0
            ? null
            : $corporation->facilities()->findOrFail($facilityId);

        $holding = $this->technologies->grant(
            $corporation,
            $technology,
            $request->origin(),
            // Left blank means "whatever this kind of card is normally worth",
            // which is what Research Control usually wants.
            $request->filled('discount_percent') ? $request->integer('discount_percent') : null,
            $facility,
            $request->string('notes')->toString() ?: null,
        );

        return back()->with('status', sprintf(
            '%s now holds %s (%s, %d%% off).',
            $corporation->name,
            $technology->name,
            $holding->origin->label(),
            $holding->discount_percent,
        ));
    }

    /**
     * Move a card, restore one a Run destroyed, or reprice its discount.
     */
    public function update(
        Game $game,
        TechnologyHolding $holding,
        UpdateTechnologyHoldingRequest $request,
    ): RedirectResponse {
        abort_if($holding->game_id !== $game->id, 404);

        if ($request->has('facility_id')) {
            $facilityId = $request->integer('facility_id');

            /** @var Facility|null $facility */
            $facility = $facilityId === 0
                ? null
                : $holding->corporation->facilities()->findOrFail($facilityId);

            $this->technologies->place($holding, $facility);
        }

        if ($request->filled('discount_percent')) {
            $holding->forceFill([
                'discount_percent' => max(0, min(100, $request->integer('discount_percent'))),
            ])->save();
        }

        if ($request->has('notes')) {
            $holding->forceFill(['notes' => $request->string('notes')->toString() ?: null])->save();
        }

        if ($request->boolean('restore') && $holding->status === TechnologyHoldingStatus::Destroyed) {
            $this->technologies->restore($holding);
        }

        return back()->with('status', $holding->technologyType->name.' updated.');
    }

    /**
     * Destroy a card a Run got to (rulebook 3.2.6).
     *
     * The row stays - "they may be able to salvage their research afterwards" -
     * so this marks rather than deletes, and update() can put it back.
     */
    public function destroy(Game $game, TechnologyHolding $holding): RedirectResponse
    {
        abort_if($holding->game_id !== $game->id, 404);

        $this->technologies->destroy($holding);

        return back()->with('status', sprintf(
            '%s destroyed in %s.',
            $holding->technologyType->name,
            $holding->facility->name ?? $holding->corporation->name,
        ));
    }
}
