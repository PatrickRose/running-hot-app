<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\StoreProtectionCardTypeRequest;
use App\Models\Game;
use App\Models\ProtectionCardType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * The game's Protection Card catalogue (rulebook 3.3.2, 3.3.3).
 *
 * Control owns it end to end. The rulebook lists cards that are on sale now,
 * rumoured, or research-only, and says outright that others exist and appear
 * once game conditions have passed - none of which the application can judge,
 * so all it does is hold what Control writes down.
 */
class ProtectionCardTypeController extends Controller
{
    public function store(Game $game, StoreProtectionCardTypeRequest $request): RedirectResponse
    {
        $cardType = $game->protectionCardTypes()->create($request->validated());

        return back()->with('status', $cardType->name.' added to the card catalogue.');
    }

    public function update(
        Game $game,
        ProtectionCardType $cardType,
        StoreProtectionCardTypeRequest $request,
    ): RedirectResponse {
        abort_if($cardType->game_id !== $game->id, 404);

        $validated = $request->validated();

        // Installed copies carry the kind so the two stacks can be ordered
        // independently, so changing it under them would put a cyber card in a
        // physical stack.
        if ($cardType->installations()->exists() && $validated['kind'] !== $cardType->kind->value) {
            throw ValidationException::withMessages([
                'kind' => 'Copies of this card are installed. Remove them before changing its kind.',
            ]);
        }

        $cardType->update($validated);

        return back()->with('status', $cardType->name.' updated.');
    }

    public function destroy(Game $game, ProtectionCardType $cardType): RedirectResponse
    {
        abort_if($cardType->game_id !== $game->id, 404);

        if ($cardType->installations()->exists()) {
            throw ValidationException::withMessages([
                'protection_card_type_id' => sprintf(
                    '%s is installed in %d Facility/Facilities. Remove those copies first.',
                    $cardType->name,
                    $cardType->installations()->count(),
                ),
            ]);
        }

        $cardType->delete();

        return back()->with('status', $cardType->name.' removed from the catalogue.');
    }
}
