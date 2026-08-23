<?php

namespace App\Actions;

use App\Enums\DiscordResourceKind;
use App\Enums\DiscordSyncStatus;
use App\Models\DiscordMemberSync;
use App\Models\DiscordResource;
use App\Models\Game;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordApiException;
use App\Support\Discord\GuildBlueprint;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a player's game roles into a game's Discord guild.
 *
 * Runs on every sign in rather than only the first, for the same reason
 * {@see ClaimCharactersForUser} does: a player moved between gangs at 11am
 * should not have to log out and back in to see the new channel.
 *
 * Only roles the application created are ever added or removed. A role Control
 * granted by hand — a helper, a colour, a joke role — survives a sync, because
 * the diff is taken against this game's own recorded roles and nothing else.
 */
class SyncDiscordRolesForUser
{
    public function __construct(private readonly DiscordApi $api) {}

    /**
     * @return DiscordSyncStatus the outcome, also recorded against the game
     */
    public function handle(Game $game, User $user): DiscordSyncStatus
    {
        if (! $game->hasDiscordGuild() || ! $this->api->isConfigured() || blank($user->discord_id)) {
            return DiscordSyncStatus::Failed;
        }

        try {
            return $this->sync($game, $user);
        } catch (DiscordApiException $exception) {
            // A player must never be unable to sign in because Discord is
            // unhappy, so this is recorded and swallowed. Control sees the
            // failure on the game's page and can re-run it.
            Log::warning('Discord role sync failed.', [
                'game_id' => $game->id,
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->record($game, $user, DiscordSyncStatus::Failed, $exception->getMessage());
        }
    }

    private function sync(Game $game, User $user): DiscordSyncStatus
    {
        $guildId = (string) $game->discord_guild_id;
        $discordId = (string) $user->discord_id;

        $member = $this->api->guildMember($guildId, $discordId);

        if ($member === null) {
            // Not in the server yet. Nothing to do but say so: the dashboard
            // turns this into a join prompt, and the next sign in tries again.
            return $this->record(
                $game,
                $user,
                DiscordSyncStatus::NotAMember,
                'This player has not joined the game\'s Discord server yet.',
            );
        }

        /** @var array<string, string> $managed key to snowflake */
        $managed = DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('kind', DiscordResourceKind::Role)
            ->pluck('discord_id', 'key')
            ->all();

        $wanted = [];

        foreach ((new GuildBlueprint($game))->roleKeysForUser($user) as $key) {
            if (isset($managed[$key])) {
                $wanted[] = $managed[$key];
            }
        }

        $held = array_map('strval', $member['roles'] ?? []);
        $reason = sprintf('Running Hot: role sync for %s (game #%d)', $user->name, $game->id);

        foreach (array_diff($wanted, $held) as $roleId) {
            $this->api->addRoleToMember($guildId, $discordId, $roleId, $reason);
        }

        // Only ever take back a role this game created. Anything else the
        // player holds is none of the application's business.
        $toRemove = array_intersect(array_diff($held, $wanted), array_values($managed));

        foreach ($toRemove as $roleId) {
            $this->api->removeRoleFromMember($guildId, $discordId, $roleId, $reason);
        }

        return $this->record($game, $user, DiscordSyncStatus::Synced, null, $wanted);
    }

    /**
     * @param  array<int, string>  $roleIds
     */
    private function record(
        Game $game,
        User $user,
        DiscordSyncStatus $status,
        ?string $message = null,
        array $roleIds = [],
    ): DiscordSyncStatus {
        DiscordMemberSync::query()->updateOrCreate(
            ['game_id' => $game->id, 'user_id' => $user->id],
            [
                'status' => $status,
                'message' => $message,
                'role_ids' => array_values($roleIds),
                'synced_at' => now(),
            ],
        );

        return $status;
    }
}
