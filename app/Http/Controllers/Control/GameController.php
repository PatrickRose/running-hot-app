<?php

namespace App\Http\Controllers\Control;

use App\Actions\CreateDefaultRoster;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\StoreGameRequest;
use App\Http\Requests\Control\UpdateGameWebhookRequest;
use App\Models\Game;
use App\Services\Discord\DiscordApi;
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
            // Whether creating a game can offer to build its Discord server.
            'botConfigured' => app(DiscordApi::class)->isConfigured(),
        ]);
    }

    public function store(StoreGameRequest $request, CreateDefaultRoster $roster): RedirectResponse
    {
        $game = Game::create($request->gameAttributes());

        // Built before handing over to Discord: provisioning permissions the
        // team channels from the roster, so the teams have to exist by then.
        $created = $request->shouldCreateDefaultRoster() ? $roster->handle($game) : null;

        // Setting the Discord server up is the rest of creating a game, so go
        // straight on to it: the bot-add flow ends by provisioning, which is
        // what produces the announcement webhook.
        if ($request->shouldConnectDiscord()) {
            return to_route('control.games.discord.connect', $game);
        }

        $status = $created === null ? 'Game created.' : sprintf(
            'Game created with %d corporations, %d gangs and %d characters.',
            $created['corporations'],
            $created['gangs'],
            $created['characters'],
        );

        return to_route('control.games.show', $game)->with('status', $status);
    }

    public function show(Game $game, GamePresenter $presenter): Response
    {
        return Inertia::render('control/games/show', [
            'game' => $presenter->summary($game),
            'trackers' => $presenter->trackers($game),
            'adjustments' => $presenter->recentAdjustments($game),
            'discordSyncs' => $presenter->discordMemberSyncs($game),
            // Optional, so opening the panel never calls Discord. Control asks
            // for it with a partial reload when they want to pick a server the
            // bot is already in.
            'discordGuilds' => Inertia::optional(fn (): array => $presenter->botGuilds()),
        ]);
    }

    /**
     * Point a game at a different Discord channel.
     */
    public function updateWebhook(Game $game, UpdateGameWebhookRequest $request): RedirectResponse
    {
        $game->update($request->validated());

        return back()->with('status', 'Discord webhook updated.');
    }

    public function finish(Game $game, TurnEngine $engine): RedirectResponse
    {
        $engine->finish($game);

        return back()->with('status', 'Game finished.');
    }
}
