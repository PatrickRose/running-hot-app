<?php

namespace App\Http\Controllers;

use App\Enums\DiscordSyncStatus;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\DiscordMemberSync;
use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The player's view: the clock, and their own trackers.
     */
    public function __invoke(Request $request, GamePresenter $presenter): Response
    {
        $game = Game::query()
            ->where('status', GameStatus::Running)
            ->latest('id')
            ->first();

        $characters = $game === null ? [] : Character::query()
            ->where('game_id', $game->id)
            ->where('user_id', $request->user()?->id)
            ->with('gang:id,name,notoriety', 'corporation:id,name,income,political_will')
            ->orderBy('name')
            ->get()
            ->map(fn (Character $character): array => [
                'id' => $character->id,
                'name' => $character->name,
                'role_label' => $character->role->label(),
                'team' => $character->gang->name ?? $character->corporation?->name,
                'credits' => $character->credits,
                'wounds' => $character->wounds,
                'tags' => $character->tags,
                'body' => $character->body,
                'brawn' => $character->brawn,
                'hack' => $character->hack,
                'incapacitated' => $character->isIncapacitated(),
                'gang' => $character->gang === null ? null : [
                    'name' => $character->gang->name,
                    'notoriety' => $character->gang->notoriety,
                ],
                'corporation' => $character->corporation === null ? null : [
                    'name' => $character->corporation->name,
                    'income' => $character->corporation->income,
                    'political_will' => $character->corporation->political_will,
                ],
            ])->all();

        return Inertia::render('dashboard', [
            'game' => $game === null ? null : $presenter->summary($game),
            'characters' => $characters,
            'isControl' => (bool) $request->user()?->isControl(),
            'discordJoin' => $this->discordJoinPrompt($request, $game),
        ]);
    }

    /**
     * Where to send a player who has signed in but is not in the game's Discord
     * server, and so cannot see any of their team's channels.
     *
     * Read from the recorded outcome of the last role sync rather than asking
     * Discord, so opening the dashboard never fans out to the API.
     *
     * @return array<string, mixed>|null
     */
    private function discordJoinPrompt(Request $request, ?Game $game): ?array
    {
        $user = $request->user();

        if ($game === null || $user === null || ! $game->hasDiscordGuild()) {
            return null;
        }

        $sync = DiscordMemberSync::query()
            ->where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->first();

        if ($sync?->status !== DiscordSyncStatus::NotAMember) {
            return null;
        }

        return [
            'invite_url' => $game->discord_invite_url,
            'game_name' => $game->name,
        ];
    }
}
