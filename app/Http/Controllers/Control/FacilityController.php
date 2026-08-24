<?php

namespace App\Http\Controllers\Control;

use App\Actions\RequisitionFacility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\InstallProtectionCardRequest;
use App\Http\Requests\Control\ReorderProtectionCardsRequest;
use App\Http\Requests\Control\StoreFacilityRequest;
use App\Http\Requests\Control\UpdateFacilityRequest;
use App\Http\Requests\Control\UpdateSecurityDirectionRequest;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Services\FacilityDefenceService;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Facility Defence from Control's side (rulebook 3.3).
 *
 * Every write lives here rather than on a player-facing route: Security tells
 * Control what they are doing, exactly as they do at the table with a
 * requisition slip and a meeple. The rules are still enforced - a full stack is
 * refused, a reorder is charged - and Control overrides by moving Credits or
 * editing the Facility rather than by the application looking the other way.
 */
class FacilityController extends Controller
{
    public function __construct(
        private readonly FacilityDefenceService $defence,
        private readonly RequisitionFacility $requisition,
    ) {}

    public function index(Game $game, GamePresenter $presenter): Response
    {
        return Inertia::render('control/games/facilities', [
            'game' => $presenter->summary($game),
            'facilities' => $presenter->facilities($game),
            'facilityTypes' => $presenter->facilityTypes($game),
            'protectionCards' => $presenter->protectionCardTypes($game),
        ]);
    }

    public function store(Game $game, StoreFacilityRequest $request): RedirectResponse
    {
        /** @var Corporation $corporation */
        $corporation = $game->corporations()->findOrFail($request->integer('corporation_id'));

        /** @var FacilityType $type */
        $type = $game->facilityTypes()->findOrFail($request->integer('facility_type_id'));

        $name = $request->string('name')->toString();

        if ($corporation->facilities()->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => $corporation->name.' already has a Facility called that.',
            ]);
        }

        // The type sheet's price unless Control names another: MCM's
        // Construction Leader technology is a discount on exactly this, and a
        // game giving a Facility away sets zero.
        $cost = $request->has('cost')
            ? (int) $request->integer('cost')
            : $type->build_cost;

        $facility = $this->requisition->handle(
            $corporation,
            $type,
            $name,
            $cost,
            $request->user(),
            immediate: $request->string('mode')->toString() === 'immediate',
        );

        return back()->with('status', sprintf(
            '%s %s.',
            $facility->name,
            $facility->isAvailableOnTurn($game->currentTurn()?->number)
                ? 'is open'
                : 'is building, and opens on turn '.$facility->available_from_turn,
        ));
    }

    public function update(Game $game, Facility $facility, UpdateFacilityRequest $request): RedirectResponse
    {
        abort_if($facility->game_id !== $game->id, 404);

        $facility->update($request->validated());

        return back()->with('status', $facility->name.' updated.');
    }

    public function destroy(Game $game, Facility $facility): RedirectResponse
    {
        abort_if($facility->game_id !== $game->id, 404);

        $facility->delete();

        return back()->with('status', $facility->name.' removed.');
    }

    /**
     * Install a card at the outermost slot of its own stack (rulebook 3.3.4).
     */
    public function installCard(
        Game $game,
        Facility $facility,
        InstallProtectionCardRequest $request,
    ): RedirectResponse {
        abort_if($facility->game_id !== $game->id, 404);

        /** @var ProtectionCardType $cardType */
        $cardType = $game->protectionCardTypes()->findOrFail(
            $request->integer('protection_card_type_id'),
        );

        $this->defence->install($facility, $cardType);

        return back()->with('status', sprintf(
            '%s installed at the outermost %s slot of %s.',
            $cardType->name,
            $cardType->kind->label(),
            $facility->name,
        ));
    }

    /**
     * Reorder one stack, at 1 Credit per card that has to move.
     */
    public function reorderCards(
        Game $game,
        Facility $facility,
        ReorderProtectionCardsRequest $request,
    ): RedirectResponse {
        abort_if($facility->game_id !== $game->id, 404);

        $cost = $this->defence->reorder(
            $facility,
            $request->kind(),
            $request->order(),
            $request->user(),
        );

        return back()->with('status', $cost === 0
            ? 'Order unchanged, so nothing was charged.'
            : sprintf('Reordered for %d Credit(s).', $cost));
    }

    /**
     * Remove a card. The first from a Facility each turn is free.
     */
    public function removeCard(
        Game $game,
        Facility $facility,
        FacilityProtectionCard $card,
    ): RedirectResponse {
        abort_if($facility->game_id !== $game->id, 404);
        abort_if($card->facility_id !== $facility->id, 404);

        $name = $card->cardType->name;
        $cost = $this->defence->remove($card, request()->user());

        return back()->with('status', sprintf(
            '%s removed from %s%s.',
            $name,
            $facility->name,
            $cost === 0 ? ' for free' : ' for 1 Credit',
        ));
    }

    /**
     * Direct Security at this Facility, and set the budget on it
     * (rulebook 3.3.5).
     */
    public function updateSecurity(
        Game $game,
        Facility $facility,
        UpdateSecurityDirectionRequest $request,
    ): RedirectResponse {
        abort_if($facility->game_id !== $game->id, 404);

        $turn = $game->currentTurn();

        if ($turn === null) {
            throw ValidationException::withMessages([
                'security_directed' => 'The game has not started, so there is no turn to direct Security in.',
            ]);
        }

        if ($request->has('security_budget')) {
            $this->defence->setSecurityBudget(
                $facility,
                (int) $request->integer('security_budget'),
                $turn,
                $request->user(),
            );
        }

        if ($request->has('security_budget_spent')) {
            $facility->stateForTurn($turn)->forceFill([
                'security_budget_spent' => (int) $request->integer('security_budget_spent'),
            ])->save();
        }

        if ($request->has('security_directed')) {
            $this->defence->directSecurity($facility, $request->boolean('security_directed'), $turn);
        }

        return back()->with('status', 'Security orders updated for '.$facility->name.'.');
    }
}
