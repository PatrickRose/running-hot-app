<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\StoreGameRequest;
use App\Models\Game;
use App\Services\TurnEngine;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function index(GamePresenter $presenter): Response
    {
        $games = Game::query()
            ->latest('id')
            ->get()
            ->map(fn (Game $game): array => $presenter->summary($game))
            ->all();

        return Inertia::render('control/games/index', [
            'games' => $games,
        ]);
    }

    public function store(StoreGameRequest $request): RedirectResponse
    {
        $game = Game::create($request->validated());

        return to_route('control.games.show', $game)
            ->with('status', 'Game created.');
    }

    public function show(Game $game, GamePresenter $presenter): Response
    {
        return Inertia::render('control/games/show', [
            'game' => $presenter->summary($game),
            'trackers' => $presenter->trackers($game),
            'adjustments' => $presenter->recentAdjustments($game),
        ]);
    }

    public function finish(Game $game, TurnEngine $engine): RedirectResponse
    {
        $engine->finish($game);

        return back()->with('status', 'Game finished.');
    }
}
