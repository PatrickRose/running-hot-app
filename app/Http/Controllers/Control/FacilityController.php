<?php

namespace App\Http\Controllers\Control;

use App\Actions\ProvisionFacilityChannels;
use App\Actions\PublishFacilityList;
use App\Actions\RequisitionFacility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\InstallProtectionCardRequest;
use App\Http\Requests\Control\ReorderProtectionCardsRequest;
use App\Http\Requests\Control\StoreFacilityRequest;
use App\Http\Requests\Control\UpdateFacilityRequest;
use App\Http\Requests\Control\UpdateSecurityBudgetRequest;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Services\FacilityDefenceService;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Facility Defence from Control's side (rulebook 3.3).
 *
 * Players have routes of their own for most of this now - Security arranges
 * their stacks and a CEO requisitions a build at /facilities - and these are
 * Control's versions of the same acts, with the overrides only Control has: a
 * build at any price, nought included, and one that opens at once. The rules
 * are still enforced - a full stack is refused, a reorder is charged - and
 * Control overrides by moving Credits or editing the Facility rather than by
 * the application looking the other way.
 */
class FacilityController extends Controller
{
    public function __construct(
        private readonly FacilityDefenceService $defence,
        private readonly RequisitionFacility $requisition,
        private readonly PublishFacilityList $facilityList,
        private readonly ProvisionFacilityChannels $facilityChannels,
    ) {}

    public function index(Game $game, GamePresenter $presenter): Response
    {
        return Inertia::render('control/games/facilities', [
            'game' => $presenter->controlSummary($game),
            'facilities' => $presenter->facilities($game),
            'plotFacilities' => $presenter->plotFacilities($game),
            'facilityTypes' => $presenter->facilityTypes($game),
            'protectionCards' => $presenter->protectionCardTypes($game),
            'cardHoldings' => $presenter->protectionCardHoldings($game),
            'facilityList' => $presenter->facilityList($game),
        ]);
    }

    /**
     * Publish the Facility list to the game's #facility-list channel.
     *
     * Control's call rather than automatic, because this is the application
     * telling every player in the game something at once. Once it is up, the
     * turn engine keeps it current.
     */
    public function publishList(Game $game): RedirectResponse
    {
        $result = $this->facilityList->handle($game);

        return back()->with('status', $result['action'] === 'posted'
            ? 'Facility list posted to #facility-list.'
            : 'Facility list updated in #facility-list.');
    }

    public function store(Game $game, StoreFacilityRequest $request): RedirectResponse
    {
        /** @var FacilityType $type */
        $type = $game->facilityTypes()->findOrFail($request->integer('facility_type_id'));

        $name = $request->string('name')->toString();

        // No Corporation names a Plot Facility: Control builds it, it belongs
        // to nobody in the roster, and the Runners hit it for the plot's sake.
        $corporationId = $request->input('corporation_id');

        if ($corporationId === null || $corporationId === '') {
            $facility = $this->storePlotFacility($game, $type, $name);

            return back()->with('status', $facility->name.' is open.');
        }

        /** @var Corporation $corporation */
        $corporation = $game->corporations()->findOrFail((int) $corporationId);

        // What the Corporation would pay itself - the sheet's price less its
        // build discount - unless Control names another, and a game giving a
        // Facility away sets zero. A blank box is "no other", which is why
        // this asks filled() rather than has().
        $cost = $request->filled('cost')
            ? (int) $request->integer('cost')
            : $corporation->facilityBuildCost($type);

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

    /**
     * Build a Plot Facility.
     *
     * The uniqueness check is by hand for the reason the Corporation one is:
     * the table's unique key is (corporation_id, name), and two null
     * corporation_ids are distinct to the database, so nothing would stop a
     * second Independent building called the same thing. Two identically named
     * targets on the list every Runner chooses from is exactly the confusion
     * the Corporation check exists to avoid.
     */
    private function storePlotFacility(Game $game, FacilityType $type, string $name): Facility
    {
        $taken = $game->facilities()->plot()->where('name', $name)->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => 'There is already a Plot Facility called that.',
            ]);
        }

        return $this->requisition->buildForControl($game, $type, $name);
    }

    /**
     * Set the Credits a Corporation takes off every Facility it builds.
     *
     * MCM's Construction Leader is the 2 seeded here and the only discount the
     * game prints. It is a number rather than read off the technology so that
     * Control can rule on it: a Run that destroys the card, a Corporation that
     * steals both pieces, or a game that simply wants it gone.
     */
    public function updateBuildDiscount(Game $game, Corporation $corporation, Request $request): RedirectResponse
    {
        abort_if($corporation->game_id !== $game->id, 404);

        $validated = $request->validate([
            'facility_build_discount' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $corporation->update($validated);

        return back()->with('status', sprintf(
            '%s now builds Facilities %d Credits cheaper.',
            $corporation->name,
            $corporation->facility_build_discount,
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
     * Build this Facility's Discord channels now.
     *
     * They are created by a queued job when the Facility is built, but that job
     * is fail-soft: if Discord was down at the time, the Facility simply has no
     * channels and nothing retries. A full provision run would pick it up, at
     * the cost of re-PATCHing every channel and role in the guild, so this is
     * the targeted way back. Idempotent, so pressing it twice is harmless.
     */
    public function provisionChannels(Game $game, Facility $facility): RedirectResponse
    {
        abort_if($facility->game_id !== $game->id, 404);

        if (blank($game->discord_guild_id)) {
            throw ValidationException::withMessages([
                'channels' => 'This game has no Discord server yet.',
            ]);
        }

        $created = $this->facilityChannels->handle($facility);

        return back()->with('status', $created === []
            ? $facility->name.' already has its channels.'
            : sprintf('Created %d channel(s) for %s.', count($created), $facility->name));
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
     * Set the budget on this Facility (rulebook 3.3.5).
     *
     * Directing Security is not modelled: a Security player may move it freely
     * during the Action phase, so a constraint nobody is held to was ceremony.
     * What Credits are on a Facility is the decision that survives.
     */
    public function updateSecurity(
        Game $game,
        Facility $facility,
        UpdateSecurityBudgetRequest $request,
    ): RedirectResponse {
        abort_if($facility->game_id !== $game->id, 404);

        $turn = $game->currentTurn();

        if ($turn === null) {
            throw ValidationException::withMessages([
                'security_budget' => 'The game has not started, so there is no turn to fund.',
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

        return back()->with('status', 'Security budget updated for '.$facility->name.'.');
    }
}
