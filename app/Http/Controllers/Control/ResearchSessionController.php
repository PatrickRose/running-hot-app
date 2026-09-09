<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\ResearchSession;
use App\Services\ResearchTableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Running the research table (rulebook 3.2.1).
 *
 * Dealing, the turn order and who is still playing are Research Control's:
 * "A turn order will be decided by Research Control randomly", and the phase
 * end that closes the table is called by Control too.
 *
 * The clock deals a fresh sitting as each Action phase opens, so most evenings
 * none of this is touched. It exists for the evenings that are not most
 * evenings - a re-deal after a misdeal, a turn order redrawn because somebody
 * has an effect that changes it, a player skipped because they have gone to
 * talk to a Runner.
 */
class ResearchSessionController extends Controller
{
    public function __construct(private readonly ResearchTableService $table) {}

    /**
     * Deal a new sitting, gathering and shuffling every deck first.
     */
    public function store(Game $game): RedirectResponse
    {
        $session = $this->table->openSession($game);

        return back()->with('status', sprintf(
            'Research table dealt: %d seat(s), %d cards in the pool.',
            $session->seats()->count(),
            $this->table->pool($game)->count(),
        ));
    }

    public function close(Game $game): RedirectResponse
    {
        $session = $this->sessionFor($game);

        $this->table->closeSession($session);

        return back()->with('status', 'Research table closed.');
    }

    public function randomise(Game $game): RedirectResponse
    {
        $session = $this->table->randomiseOrder($this->sessionFor($game));

        return back()->with('status', sprintf(
            'Turn order redrawn. %s starts.',
            $session->currentSeat()?->corporation->name ?? 'Nobody',
        ));
    }

    public function advance(Game $game): RedirectResponse
    {
        $seat = $this->table->advanceTurn($this->sessionFor($game));

        return back()->with('status', $seat === null
            ? 'Nobody is left at the research table.'
            : 'It is now '.$seat->corporation->name.'\'s turn.');
    }

    /**
     * Sit a Corporation down or take it out of the sitting.
     */
    public function seat(Game $game, Corporation $corporation, Request $request): RedirectResponse
    {
        abort_if($corporation->game_id !== $game->id, 404);

        $session = $this->sessionFor($game);

        if ($request->boolean('playing')) {
            $this->table->rejoin($session, $corporation);

            return back()->with('status', $corporation->name.' is at the research table.');
        }

        $this->table->leave($session, $corporation, 'Taken out by Control');

        return back()->with('status', $corporation->name.' has left the research table.');
    }

    private function sessionFor(Game $game): ResearchSession
    {
        $session = $this->table->currentSession($game);

        if ($session === null) {
            throw ValidationException::withMessages([
                'research' => 'No research table is open. Deal one first.',
            ]);
        }

        return $session;
    }
}
