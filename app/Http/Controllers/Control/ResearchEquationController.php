<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\ScoreResearchEquationRequest;
use App\Models\Game;
use App\Models\ResearchEquation;
use App\Services\ResearchTableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Control over what an equation paid (rulebook 3.2.1).
 *
 * Three verbs, and the middle one is the important one. Scoring is normally the
 * player's, done in their own time while the table carries on. Unscoring hands
 * the points back so it can be scored again - which is how a player who took
 * their points in the wrong suit is put right, and why "Control can override
 * any score" does not need a back door into the trackers. Both movements are in
 * the ledger, so the tokens on the table can always be reconciled with what the
 * application thinks.
 *
 * Voiding is for a play that should never have happened. It is marked rather
 * than deleted: the cards it spent are already gone.
 */
class ResearchEquationController extends Controller
{
    public function __construct(private readonly ResearchTableService $table) {}

    public function score(
        Game $game,
        ResearchEquation $equation,
        ScoreResearchEquationRequest $request,
    ): RedirectResponse {
        abort_if($equation->game_id !== $game->id, 404);

        $this->table->score(
            $equation,
            $request->side(),
            $request->suit(),
            $request->bonus(),
            $request->user(),
        );

        return back()->with('status', sprintf(
            'Equation #%d scored for %s.',
            $equation->id,
            $equation->corporation->name,
        ));
    }

    public function unscore(Game $game, ResearchEquation $equation, Request $request): RedirectResponse
    {
        abort_if($equation->game_id !== $game->id, 404);

        $this->table->unscore($equation, $request->user());

        return back()->with('status', sprintf(
            'Equation #%d is waiting to be scored again.',
            $equation->id,
        ));
    }

    public function void(Game $game, ResearchEquation $equation, Request $request): RedirectResponse
    {
        abort_if($equation->game_id !== $game->id, 404);

        $this->table->void(
            $equation,
            $request->string('reason')->toString() ?: null,
            $request->user(),
        );

        return back()->with('status', sprintf('Equation #%d voided.', $equation->id));
    }
}
