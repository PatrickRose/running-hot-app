<?php

namespace App\Http\Controllers;

use App\Actions\RollDice;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use App\Support\DiceRollPresenter;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Anybody holding a seat rolls some d6s and d8s, and Control reads the result.
 *
 * The rulebook has plenty of rolls outside a run - a Freelancer's special rule,
 * a ruling Control wants the dice to settle - and they were being rolled on a
 * kitchen table and typed into Discord, where nobody could see the faces. So
 * the dice are thrown here, on the server, and the result goes to the player
 * who rolled it and to Control and to nobody else.
 *
 * The seat is named in the request rather than inferred, because a player may
 * hold two and Control wants to know which of them was rolling.
 */
class DiceRollController extends Controller
{
    public function index(Request $request, GamePresenter $games, DiceRollPresenter $rolls): Response
    {
        $game = Game::current();

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dice', [
            'game' => $game === null ? null : $games->summary($game),
            'seats' => $game === null ? [] : $this->seatsFor($game, $user),
            'rolls' => $game === null ? [] : $rolls->recent($game, $user),
            'can_roll' => $game !== null && $this->mayRoll($game, $user),
            'is_control' => $game !== null && $user->isControlFor($game),
            'max_per_size' => RollDice::MAX_PER_SIZE,
        ]);
    }

    public function store(Request $request, RollDice $roller): RedirectResponse
    {
        $game = Game::current();

        abort_if($game === null, 404);

        /** @var User $user */
        $user = $request->user();

        abort_unless($this->mayRoll($game, $user), 403);

        $isControl = $user->isControlFor($game);

        $validated = $request->validate([
            // Required of a player, who is rolling as one of their seats;
            // Control rolls as Control and may leave it out.
            'character_id' => [
                Rule::requiredIf(! $isControl),
                'nullable', 'integer',
                Rule::exists('characters', 'id')->where('game_id', $game->id),
            ],
            'd6' => ['required', 'integer', 'min:0', 'max:'.RollDice::MAX_PER_SIZE],
            'd8' => ['required', 'integer', 'min:0', 'max:'.RollDice::MAX_PER_SIZE],
            'purpose' => ['nullable', 'string', 'max:255'],
        ]);

        $character = null;

        if (isset($validated['character_id'])) {
            /** @var Character $character */
            $character = $game->characters()->findOrFail($validated['character_id']);

            // Rolling as somebody else's seat would put your dice under their
            // name on Control's screen.
            abort_unless($isControl || $character->user_id === $user->id, 403);
        }

        $roll = $roller->handle(
            $game,
            (int) $validated['d6'],
            (int) $validated['d8'],
            $user,
            $character,
            $validated['purpose'] ?? null,
        );

        return back()->with('status', sprintf(
            '%d success%s. Control can see the roll.',
            $roll->successes,
            $roll->successes === 1 ? '' : 'es',
        ));
    }

    /**
     * Holding a seat in a running game, or being Control of it.
     *
     * The division "A game off the clock" draws: reading your rolls is a fact
     * about the roster, and rolling is an act, so it asks about the clock.
     */
    protected function mayRoll(Game $game, User $user): bool
    {
        if ($user->isControlFor($game)) {
            return true;
        }

        return $game->isRunning()
            && $game->characters()->where('user_id', $user->id)->exists();
    }

    /**
     * The seats this user may roll as.
     *
     * @return array<int, array{character_id: int, name: string, role_label: string}>
     */
    protected function seatsFor(Game $game, User $user): array
    {
        return $game->characters()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Character $character): array => [
                'character_id' => $character->id,
                'name' => $character->name,
                'role_label' => $character->role->label(),
            ])
            ->values()
            ->all();
    }
}
