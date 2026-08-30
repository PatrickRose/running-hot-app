<?php

namespace App\Http\Controllers\Control;

use App\Actions\CreateDefaultFacilities;
use App\Actions\CreateDefaultRoster;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\StoreGameRequest;
use App\Http\Requests\Control\UpdateGameWebhookRequest;
use App\Models\Game;
use App\Services\Discord\DiscordApi;
use App\Services\TurnEngine;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function index(Request $request, GamePresenter $presenter): Response
    {
        $user = $request->user();

        $games = Game::query()
            // Someone Control of everything sees every game; someone holding a
            // seat sees the games they were named on, and nothing else.
            ->unless($user?->isControlEverywhere(), fn ($query) => $query->whereHas(
                'controlMembers',
                fn ($members) => $members->where('user_id', $user?->id),
            ))
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

    public function store(
        StoreGameRequest $request,
        CreateDefaultRoster $roster,
        CreateDefaultFacilities $facilities,
    ): RedirectResponse {
        $game = Game::create($request->gameAttributes());

        // Whoever made the game runs it. Without this a seat holder would be
        // bounced off the panel of the game they had just created, since seats
        // are what Control of a game means for everyone but the flag holder.
        $creator = $request->user();

        if ($creator !== null && ! $creator->isControlEverywhere()) {
            $game->controlMembers()->create([
                'user_id' => $creator->id,
                'discord_username' => $creator->discord_username,
            ]);
        }

        // Built before handing over to Discord: provisioning permissions the
        // team channels from the roster, so the teams have to exist by then.
        $status = 'Game created.';

        if ($request->shouldCreateDefaultRoster()) {
            $created = $roster->handle($game);
            // After the roster, because Facilities belong to Corporations.
            $defences = $facilities->handle($game);

            $status = sprintf(
                'Game created with %d corporations, %d gangs, %d characters and %d Facilities.',
                $created['corporations'],
                $created['gangs'],
                $created['characters'],
                $defences['facilities'],
            );
        }

        // Setting the Discord server up is the rest of creating a game, so go
        // straight on to it: the bot-add flow ends by provisioning, which is
        // what produces the announcement webhook.
        if ($request->shouldConnectDiscord()) {
            return to_route('control.games.discord.connect', $game);
        }

        return to_route('control.games.show', $game)->with('status', $status);
    }

    public function show(Game $game, GamePresenter $presenter): Response
    {
        return Inertia::render('control/games/show', [
            'game' => $presenter->summary($game),
            'trackers' => $presenter->trackers($game),
            'controlMembers' => $presenter->controlMembers($game),
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
