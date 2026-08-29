<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\StoreTechnologyTypeRequest;
use App\Models\Game;
use App\Models\TechnologyType;
use Illuminate\Http\RedirectResponse;

/**
 * The technologies on a game's tech trees (rulebook 3.2.2).
 *
 * Editable during play because the rulebook asks for it: 3.2.4 has players
 * writing their own research proposals, which Research Control prices and adds
 * to the tree there and then.
 */
class TechnologyTypeController extends Controller
{
    public function store(Game $game, StoreTechnologyTypeRequest $request): RedirectResponse
    {
        $technology = $game->technologyTypes()->create($request->validated());

        return back()->with('status', $technology->name.' added to the tech tree.');
    }

    public function update(
        Game $game,
        TechnologyType $technology,
        StoreTechnologyTypeRequest $request,
    ): RedirectResponse {
        abort_if($technology->game_id !== $game->id, 404);

        $technology->update($request->validated());

        return back()->with('status', $technology->name.' updated.');
    }

    public function destroy(Game $game, TechnologyType $technology): RedirectResponse
    {
        abort_if($technology->game_id !== $game->id, 404);

        $technology->delete();

        return back()->with('status', $technology->name.' removed from the tech tree.');
    }
}
