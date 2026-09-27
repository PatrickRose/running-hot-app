<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Control taking Credits from one purse and handing them to another.
 *
 * The player route beside it spends a seat's own purse; this one spends
 * anybody's, because a ruling is not a trade. Which purses exist and whether
 * the Credits are there are still App\Services\CreditService's.
 */
class CreditTransferController extends Controller
{
    public function __invoke(Game $game, Request $request, CreditService $credits): RedirectResponse
    {
        $validated = $request->validate([
            'from_type' => ['required', Rule::in(['character', 'corporation'])],
            'from_id' => [
                'required', 'integer',
                Rule::exists($request->input('from_type') === 'corporation' ? 'corporations' : 'characters', 'id')
                    ->where('game_id', $game->id),
            ],
            'to_type' => ['required', Rule::in(['character', 'corporation'])],
            'to_id' => [
                'required', 'integer',
                Rule::exists($request->input('to_type') === 'corporation' ? 'corporations' : 'characters', 'id')
                    ->where('game_id', $game->id),
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $from = $this->purse($game, $validated['from_type'], (int) $validated['from_id']);
        $to = $this->purse($game, $validated['to_type'], (int) $validated['to_id']);
        $amount = (int) $validated['amount'];

        $credits->move($from, $to, $amount, $request->user(), $validated['reason'] ?? null);

        return back()->with('status', sprintf(
            'Moved %d %s from %s to %s.',
            $amount,
            $amount === 1 ? 'Credit' : 'Credits',
            $from->name,
            $to->name,
        ));
    }

    private function purse(Game $game, string $type, int $id): Character|Corporation
    {
        return $type === 'corporation'
            ? $game->corporations()->findOrFail($id)
            : $game->characters()->findOrFail($id);
    }
}
