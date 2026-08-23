<?php

namespace App\Jobs;

use App\Actions\SyncDiscordRolesForUser;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Syncs one player's roles across every game they are in.
 *
 * Dispatched from the Discord sign in, so it is queued: a player logging in
 * should not wait on Discord, and should certainly not see an error page if
 * Discord is down.
 */
class SyncDiscordRoles implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $userId) {}

    public function handle(SyncDiscordRolesForUser $sync, DiscordApi $api): void
    {
        if (! $api->isConfigured()) {
            // No bot configured: role assignment is simply not a feature of
            // this deployment, which is not an error worth retrying.
            return;
        }

        $user = User::query()->find($this->userId);

        if ($user === null || blank($user->discord_id)) {
            return;
        }

        $games = Game::query()
            ->whereNotNull('discord_guild_id')
            ->where('status', '!=', GameStatus::Finished)
            // Either they hold a character in it, or they are Control, who
            // belong in every game's server.
            ->when(! $user->isControl(), fn ($query) => $query->whereHas(
                'characters',
                fn ($characters) => $characters->where('user_id', $user->id),
            ))
            ->get();

        foreach ($games as $game) {
            $sync->handle($game, $user);
        }
    }
}
