<?php

namespace App\Http\Controllers\Control;

use App\Enums\CharacterRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\SetEquipmentHoldingRequest;
use App\Models\Character;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Services\EquipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * How many copies of an Equipment card a Runner is carrying (rulebook 3.4.1).
 *
 * Control's alone, and set outright - the same shape as
 * ProtectionCardHoldingController and for the same reason. Runners buy from the
 * market and from each other during Setup (2.2.1), sell what a run brought
 * back, and are handed cards by Control for a job that went well. Every one of
 * those is a conversation, so the application holds the number rather than
 * modelling any of the routes to it.
 *
 * Thin on purpose: the write itself is EquipmentService's, which is the one
 * place `equipment_holdings` is touched. A Control route must not grow its own
 * copy of a rule the run loop also depends on.
 */
class EquipmentHoldingController extends Controller
{
    public function __construct(private readonly EquipmentService $equipment) {}

    public function update(Game $game, SetEquipmentHoldingRequest $request): RedirectResponse
    {
        /** @var Character $runner */
        $runner = $game->characters()->findOrFail($request->integer('character_id'));

        // Equipment belongs to the side that runs. A CEO with a Katana in hand
        // would be a row nothing reads and a line on the page nobody can
        // explain, so it is refused here rather than quietly written.
        if (! in_array($runner->role, [CharacterRole::Runner, CharacterRole::Freelancer], true)) {
            throw ValidationException::withMessages([
                'character_id' => sprintf(
                    '%s is %s, and Equipment is carried by Runners and Freelancers.',
                    $runner->name,
                    $runner->role->label(),
                ),
            ]);
        }

        /** @var EquipmentCardType $cardType */
        $cardType = $game->equipmentCardTypes()->findOrFail($request->integer('equipment_card_type_id'));

        $copies = $request->integer('copies');

        $this->equipment->setCopiesInHand($runner, $cardType, $copies);

        return back()->with('status', sprintf(
            '%s now carries %d %s of %s.',
            $runner->name,
            $copies,
            $copies === 1 ? 'copy' : 'copies',
            $cardType->name,
        ));
    }
}
