<?php

namespace App\Http\Controllers;

use App\Enums\CharacterRole;
use App\Http\Requests\Research\PlayEquationRequest;
use App\Http\Requests\Research\ScoreEquationRequest;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\ResearchEquation;
use App\Models\ResearchSession;
use App\Services\ResearchTableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The Research player at the table (rulebook 3.2.1).
 *
 * Thin, deliberately: every rule about what an equation is, what it is worth
 * and what happens to the cards lives in App\Services\ResearchTableService and
 * App\Support\Equation. What this decides is who is asking, which is the
 * CorporationPolicy's answer, and what to say when they are done.
 */
class ResearchTableController extends Controller
{
    public function __construct(private readonly ResearchTableService $table) {}

    /**
     * Put two sets of cards up as an equation.
     *
     * The points are not taken here. The rulebook wants scoring to happen
     * "while other players are taking their turns", so this spends the cards,
     * draws back up and passes the turn on - and the equation waits.
     */
    public function play(PlayEquationRequest $request): RedirectResponse
    {
        [$corporation, $session] = $this->tableFor($request);

        $equation = $this->table->play(
            $session,
            $corporation,
            $request->left(),
            $request->right(),
        );

        return back()->with('status', $equation->balanced
            ? sprintf(
                'Equation played, and it balances — %d bonus point(s) to take. Score it when you are ready.',
                $equation->bonus,
            )
            : 'Equation played. Score it when you are ready.');
    }

    /**
     * Take the points, whenever you get round to it.
     */
    public function score(ResearchEquation $equation, ScoreEquationRequest $request): RedirectResponse
    {
        $this->table->score(
            $equation,
            $request->side(),
            $request->suit(),
            $request->bonus(),
            $request->user(),
        );

        $awards = array_filter($equation->awards ?? []);
        $description = [];

        foreach ($awards as $suit => $points) {
            $description[] = $points.' '.$suit;
        }

        return back()->with('status', $description === []
            ? 'Equation scored for nothing.'
            : 'Scored: '.implode(', ', $description).'.');
    }

    /**
     * Get up from the research table (rulebook 3.2.1).
     */
    public function leave(Request $request): RedirectResponse
    {
        [$corporation, $session] = $this->tableFor($request);

        Gate::authorize('research', $corporation);

        $this->table->leave($session, $corporation, 'Left the table');

        return back()->with('status', $corporation->name.' has left the research table.');
    }

    /**
     * Sit back down. Leaving is a choice rather than a forfeit, so coming back
     * has to be one too.
     */
    public function rejoin(Request $request): RedirectResponse
    {
        [$corporation, $session] = $this->tableFor($request);

        Gate::authorize('research', $corporation);

        $this->table->rejoin($session, $corporation);

        return back()->with('status', $corporation->name.' is back at the research table.');
    }

    /**
     * The Corporation this player plays for, and the sitting in progress.
     *
     * @return array{0: Corporation, 1: ResearchSession}
     */
    private function tableFor(Request $request): array
    {
        $game = Game::current();

        abort_if($game === null, 404);

        $user = $request->user();
        abort_if($user === null, 403);

        $corporation = $user->corporationIn($game, CharacterRole::Research)
            ?? $user->corporationIn($game);

        abort_if($corporation === null, 403);

        $session = $this->table->currentSession($game);

        if ($session === null) {
            throw ValidationException::withMessages([
                'equation' => 'The research table is not open. Research Control deals the cards.',
            ]);
        }

        return [$corporation, $session];
    }
}
