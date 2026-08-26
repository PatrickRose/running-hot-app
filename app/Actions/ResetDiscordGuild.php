<?php

namespace App\Actions;

use App\Enums\DiscordProvisionStatus;
use App\Enums\DiscordResourceKind;
use App\Models\Game;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordApiException;
use App\Services\Discord\DiscordNotConfiguredException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Empties a game's Discord guild so it can be provisioned from nothing.
 *
 * The deliberate exception to "provisioning reconciles, it never resets". Every
 * other path through this application is additive on purpose, because a gang
 * leaving a game must not take its channel history with it and a mid-game
 * re-run must be safe. This one is the opposite of all that, and exists for a
 * test server that has accumulated years of half-finished runs and needs to go
 * back to the state a brand new server is in.
 *
 * It deletes everything in the guild the bot is allowed to delete: every
 * channel, and every role except the two kinds Discord will not part with:
 * the default role (@everyone, which carries the guild's own snowflake) and a
 * managed role, which belongs to an integration. That is far more than the
 * application ever created: Control's own channels go too, which is the point
 * of a wipe, and the reason nothing calls this without Control typing the
 * game's name out first.
 *
 * The guild itself survives, along with its members, its name and its icon.
 * Discord does not let a bot delete a server it did not create, and losing the
 * invite links everybody has already used would be worse than the mess.
 *
 * A refusal on one object never stops the run. A guild always has at least one
 * role the bot cannot touch, so treating a 403 as fatal would mean the wipe
 * failed every time; they are counted and reported instead.
 */
class ResetDiscordGuild
{
    public function __construct(private readonly DiscordApi $api) {}

    /**
     * @return array{channels_deleted: int, roles_deleted: int, refused: int}
     */
    public function handle(Game $game): array
    {
        if (blank($game->discord_guild_id)) {
            throw new RuntimeException('This game has no Discord server set.');
        }

        if (! $this->api->isConfigured()) {
            throw new DiscordNotConfiguredException(
                'DISCORD_BOT_TOKEN is not set, so the application cannot clear a Discord server.'
            );
        }

        $guildId = $game->discord_guild_id;
        $reason = sprintf('Running Hot: clearing "%s" (game #%d) for a fresh provision', $game->name, $game->id);

        $this->api->assertBotIsInGuild($guildId);

        $tally = ['channels_deleted' => 0, 'roles_deleted' => 0, 'refused' => 0];

        $this->deleteChannels($guildId, $reason, $tally);
        $this->deleteRoles($guildId, $reason, $tally);

        $this->forgetEverything($game, $tally);

        Log::warning('Discord guild cleared.', ['game_id' => $game->id, 'guild_id' => $guildId, ...$tally]);

        return $tally;
    }

    /**
     * Children before categories.
     *
     * Deleting a category does not delete what is inside it — Discord moves the
     * children up to the top level instead — so taking the categories first
     * would leave a guild full of orphaned channels to sweep up afterwards.
     *
     * @param  array{channels_deleted: int, roles_deleted: int, refused: int}  $tally
     */
    private function deleteChannels(string $guildId, string $reason, array &$tally): void
    {
        $channels = $this->api->channels($guildId);

        usort($channels, fn (array $a, array $b): int => $this->isCategory($a) <=> $this->isCategory($b));

        foreach ($channels as $channel) {
            $deleted = $this->attempt(
                function () use ($channel, $reason): void {
                    $this->api->deleteChannel((string) $channel['id'], $reason);
                },
                'channel',
                (string) ($channel['name'] ?? $channel['id']),
                $tally,
            );

            if ($deleted) {
                $tally['channels_deleted']++;
            }
        }
    }

    /**
     * @param  array{channels_deleted: int, roles_deleted: int, refused: int}  $tally
     */
    private function deleteRoles(string $guildId, string $reason, array &$tally): void
    {
        foreach ($this->api->roles($guildId) as $role) {
            $id = (string) $role['id'];

            // @everyone shares the guild's snowflake and cannot be deleted, and
            // a managed role belongs to an integration — including the bot's
            // own, which it would be deleting out from under itself.
            if ($id === $guildId || ($role['managed'] ?? false) === true) {
                continue;
            }

            $deleted = $this->attempt(
                function () use ($guildId, $id, $reason): void {
                    $this->api->deleteRole($guildId, $id, $reason);
                },
                'role',
                (string) ($role['name'] ?? $id),
                $tally,
            );

            if ($deleted) {
                $tally['roles_deleted']++;
            }
        }
    }

    /**
     * Run one deletion, counting a refusal rather than abandoning the wipe.
     *
     * A 403 is a role above the bot's own in the hierarchy, or a channel the
     * bot cannot manage; a 404 is something that went away between the listing
     * and now, which is the outcome we wanted anyway. Neither is worth stopping
     * for, and stopping would leave the guild half-cleared.
     *
     * @param  callable(): void  $call
     * @param  array{channels_deleted: int, roles_deleted: int, refused: int}  $tally
     */
    private function attempt(callable $call, string $what, string $name, array &$tally): bool
    {
        try {
            $call();

            return true;
        } catch (DiscordApiException $exception) {
            if ($exception->isNotFound()) {
                return false;
            }

            if (! $exception->isForbidden()) {
                throw $exception;
            }

            $tally['refused']++;

            Log::warning('Discord refused to delete something during a reset.', [
                'kind' => $what,
                'name' => $name,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Put the game back where it was before it had ever seen a Discord server.
     *
     * The snowflakes are the important part: every one of them now points at
     * something that no longer exists, so a reconcile that kept them would
     * spend its first run patching deleted objects. The webhook and invite go
     * with them, because both lived in channels that have just been deleted and
     * a stale announcement URL fails silently, which is the worst way to fail.
     *
     * @param  array{channels_deleted: int, roles_deleted: int, refused: int}  $tally
     */
    private function forgetEverything(Game $game, array $tally): void
    {
        $game->discordResources()->delete();
        $game->discordMemberSyncs()->delete();

        $game->update([
            'discord_webhook_url' => null,
            'discord_invite_url' => null,
            'discord_provision_status' => DiscordProvisionStatus::Idle,
            'discord_provision_message' => sprintf(
                'Server cleared: %d channel(s) and %d role(s) deleted%s. Provision to build it again.',
                $tally['channels_deleted'],
                $tally['roles_deleted'],
                $tally['refused'] > 0
                    ? sprintf(', %d refused by Discord', $tally['refused'])
                    : '',
            ),
            'discord_provisioned_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    private function isCategory(array $channel): bool
    {
        return (int) ($channel['type'] ?? -1) === DiscordResourceKind::Category->channelType();
    }
}
