<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * One player paying another.
 *
 * Thin, like EquipmentTransferController beside it. Whether the seat is yours
 * is App\Policies\CharacterPolicy's; which purse it spends, who may be paid and
 * whether the Credits are there are App\Services\CreditService's.
 */
class CreditTransferController extends Controller
{
    public function __invoke(Request $request, CreditService $credits): RedirectResponse
    {
        $game = Game::current($request->user());

        abort_if($game === null, 404);

        $validated = $request->validate([
            'from_character_id' => [
                'required', 'integer',
                Rule::exists('characters', 'id')->where('game_id', $game->id),
            ],
            'to_type' => ['required', Rule::in(['character', 'corporation'])],
            'to_id' => [
                'required', 'integer',
                Rule::exists($request->input('to_type') === 'corporation' ? 'corporations' : 'characters', 'id')
                    ->where('game_id', $game->id),
            ],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        /** @var Character $from */
        $from = $game->characters()->findOrFail($validated['from_character_id']);

        Gate::authorize('giveCredits', $from);

        /** @var Character|Corporation $to */
        $to = $validated['to_type'] === 'corporation'
            ? $game->corporations()->findOrFail($validated['to_id'])
            : $game->characters()->findOrFail($validated['to_id']);

        $amount = (int) $validated['amount'];

        $credits->give($from, $to, $amount, $request->user());

        return back()->with('status', sprintf(
            '%s pays %s %d %s.',
            $credits->purseFor($from)->name ?? $from->name,
            $to->name,
            $amount,
            $amount === 1 ? 'Credit' : 'Credits',
        ));
    }
}
