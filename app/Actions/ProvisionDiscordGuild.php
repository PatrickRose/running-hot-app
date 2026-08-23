<?php

namespace App\Actions;

use App\Enums\DiscordProvisionStatus;
use App\Enums\DiscordResourceKind;
use App\Models\DiscordResource;
use App\Models\Game;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordApiException;
use App\Services\Discord\DiscordNotConfiguredException;
use App\Support\Discord\GuildBlueprint;
use App\Support\Discord\PlannedChannel;
use App\Support\Discord\PlannedOverwrite;
use App\Support\Discord\PlannedRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Brings a game's Discord guild in line with its blueprint.
 *
 * Reconciles rather than resets. Every object the application has made is
 * recorded in `discord_resources` against a stable key, so a re-run renames
 * what drifted, rebuilds what somebody deleted by hand, and adds whatever the
 * roster has grown since. Anything in the guild that the application did not
 * create is never touched: Control's own channels are Control's business, and
 * a mid-game run must be safe.
 *
 * The guild itself is never created here. Discord's Create Guild endpoint only
 * works for bots in fewer than ten guilds and yields a server with no members
 * in it, so Control makes the server, invites the bot, and hands over the
 * snowflake.
 */
class ProvisionDiscordGuild
{
    public function __construct(private readonly DiscordApi $api) {}

    /**
     * @return array<string, int> counts of what changed, for the log
     */
    public function handle(Game $game): array
    {
        if (blank($game->discord_guild_id)) {
            throw new RuntimeException('This game has no Discord server set.');
        }

        if (! $this->api->isConfigured()) {
            throw new DiscordNotConfiguredException(
                'DISCORD_BOT_TOKEN is not set, so the application cannot provision a Discord server.'
            );
        }

        $guildId = $game->discord_guild_id;
        $blueprint = new GuildBlueprint($game);
        $reason = sprintf('Running Hot: provisioning "%s" (game #%d)', $game->name, $game->id);

        $this->assertBotIsInGuild($guildId);

        $tally = ['roles_created' => 0, 'roles_updated' => 0, 'channels_created' => 0, 'channels_updated' => 0];

        $roleIds = $this->reconcileRoles($game, $guildId, $blueprint, $reason, $tally);
        $this->giveBotTheControlRole($guildId, $roleIds, $reason);
        $this->reconcileChannels($game, $guildId, $blueprint, $roleIds, $reason, $tally);
        $this->ensureAnnouncementWebhook($game, $reason);
        $this->ensureInvite($game, $reason);

        Log::info('Discord guild provisioned.', ['game_id' => $game->id, 'guild_id' => $guildId, ...$tally]);

        return $tally;
    }

    /**
     * Fail fast, and in words Control can act on, if the bot is not in the guild.
     *
     * This is by far the most common setup mistake, and Discord's own answer —
     * a bare "Unknown Guild" 404 — reads like the server does not exist. With a
     * token that authenticates (an invalid one is a 401), a 404 here means only
     * one thing: this bot is not a member. Saying so, and pointing at the
     * invite, saves the guess.
     */
    private function assertBotIsInGuild(string $guildId): void
    {
        try {
            $this->api->guild($guildId);
        } catch (DiscordApiException $exception) {
            if (! $exception->isNotFound()) {
                throw $exception;
            }

            throw new DiscordApiException(
                'The bot is not a member of this Discord server (Discord answered "Unknown Guild"). '
                .'Use "Add the bot to a Discord server" above, which also picks up the right server ID.',
                $exception->status,
                $exception->discordCode,
            );
        }
    }

    /**
     * @param  array<string, int>  $tally
     * @return array<string, string> role key to snowflake
     */
    private function reconcileRoles(
        Game $game,
        string $guildId,
        GuildBlueprint $blueprint,
        string $reason,
        array &$tally,
    ): array {
        $live = $this->bySnowflake($this->api->roles($guildId));
        $recorded = $this->recorded($game, DiscordResourceKind::Role);

        $roleIds = [];

        foreach ($blueprint->roles() as $key => $planned) {
            $resource = $recorded->get($key);
            $existing = $resource === null ? null : ($live[self::snowflakeKey($resource->discord_id)] ?? null);

            if ($existing === null) {
                // Either never created, or somebody deleted it in Discord.
                $created = $this->api->createRole($guildId, $planned->payload(), $reason);
                $this->record($game, DiscordResourceKind::Role, $key, (string) $created['id'], $planned->name);
                $roleIds[$key] = (string) $created['id'];
                $tally['roles_created']++;

                continue;
            }

            $roleIds[$key] = $resource->discord_id;

            if ($this->roleHasDrifted($existing, $planned)) {
                $this->api->updateRole($guildId, $resource->discord_id, $planned->payload(), $reason);
                $resource->update(['name' => $planned->name]);
                $tally['roles_updated']++;
            }
        }

        return $roleIds;
    }

    /**
     * Put the bot in the Control role before it starts making channels.
     *
     * Discord drops every permission in a channel its caller cannot view, and a
     * private channel denies View Channel to @everyone — which is the only way
     * the bot has it. So locking a category to one team locks the bot out of it
     * too, and it cannot then create the channels that belong inside it.
     *
     * Every private channel here already grants Control, so holding that role
     * is all the access the bot needs, and it repairs a guild provisioned
     * before this existed rather than needing anything deleted.
     *
     * @param  array<string, string>  $roleIds
     */
    private function giveBotTheControlRole(string $guildId, array $roleIds, string $reason): void
    {
        $controlRoleId = $roleIds[GuildBlueprint::ROLE_CONTROL] ?? null;

        if ($controlRoleId === null) {
            return;
        }

        $botUserId = (string) $this->api->currentUser()['id'];

        try {
            // Idempotent: Discord answers 204 whether or not it already had it.
            $this->api->addRoleToMember($guildId, $botUserId, $controlRoleId, $reason);
        } catch (DiscordApiException $exception) {
            if (! $exception->isForbidden()) {
                throw $exception;
            }

            throw new DiscordApiException(
                'The bot could not give itself the Control role, which it needs in order to manage the '
                .'game\'s private channels. Discord refuses this when the role sits above the bot\'s own '
                .'in the server\'s role list — drag the bot\'s role above Control and provision again.',
                $exception->status,
                $exception->discordCode,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $existing
     */
    private function roleHasDrifted(array $existing, PlannedRole $planned): bool
    {
        return ($existing['name'] ?? null) !== $planned->name
            || (int) ($existing['color'] ?? 0) !== $planned->colour
            || (bool) ($existing['hoist'] ?? false) !== $planned->hoist
            || (bool) ($existing['mentionable'] ?? false) !== $planned->mentionable;
    }

    /**
     * @param  array<string, string>  $roleIds
     * @param  array<string, int>  $tally
     */
    private function reconcileChannels(
        Game $game,
        string $guildId,
        GuildBlueprint $blueprint,
        array $roleIds,
        string $reason,
        array &$tally,
    ): void {
        $live = $this->bySnowflake($this->api->channels($guildId));
        $recorded = $this->recorded($game);

        // Snowflakes of the categories created in this pass, so a channel can
        // be parented to one made moments ago.
        $channelIds = [];

        foreach ($blueprint->channels() as $planned) {
            $resource = $recorded->get($planned->key);
            $existing = $resource === null ? null : ($live[self::snowflakeKey($resource->discord_id)] ?? null);

            $payload = $this->channelPayload($planned, $guildId, $roleIds, $channelIds);

            if ($existing === null) {
                $created = $this->lockoutAware(
                    $planned,
                    fn (): array => $this->api->createChannel($guildId, $payload, $reason),
                );
                $this->record($game, $planned->kind, $planned->key, (string) $created['id'], $planned->name);
                $channelIds[$planned->key] = (string) $created['id'];
                $tally['channels_created']++;

                continue;
            }

            $channelIds[$planned->key] = $resource->discord_id;

            // Permission overwrites are re-sent every run rather than diffed.
            // A team's channel silently losing its lock is far worse than one
            // extra PATCH, and a wrong overwrite is invisible until a player
            // reads something they should not have.
            $this->lockoutAware(
                $planned,
                fn (): array => $this->api->updateChannel($resource->discord_id, $payload, $reason),
            );

            if ($resource->name !== $planned->name) {
                $resource->update(['name' => $planned->name]);
            }

            $tally['channels_updated']++;
        }
    }

    /**
     * @param  array<string, string>  $roleIds
     * @param  array<string, string>  $channelIds
     * @return array<string, mixed>
     */
    private function channelPayload(
        PlannedChannel $planned,
        string $guildId,
        array $roleIds,
        array $channelIds,
    ): array {
        $payload = [
            'name' => $planned->name,
            'type' => $planned->kind->channelType(),
        ];

        if ($planned->parentKey !== null && isset($channelIds[$planned->parentKey])) {
            $payload['parent_id'] = $channelIds[$planned->parentKey];
        }

        // Discord rejects a topic on a voice channel, so only text gets one.
        if ($planned->topic !== null && $planned->kind === DiscordResourceKind::TextChannel) {
            $payload['topic'] = $planned->topic;
        }

        $overwrites = [];

        foreach ($planned->overwrites as $overwrite) {
            // The guild's default role shares the guild's snowflake, which is
            // how Discord expresses "@everyone".
            $target = $overwrite->target === PlannedOverwrite::EVERYONE
                ? $guildId
                : ($roleIds[$overwrite->target] ?? null);

            if ($target === null) {
                continue;
            }

            $overwrites[] = [
                'id' => $target,
                'type' => DiscordApi::OVERWRITE_ROLE,
                'allow' => (string) $overwrite->allow,
                'deny' => (string) $overwrite->deny,
            ];
        }

        $payload['permission_overwrites'] = $overwrites;

        return $payload;
    }

    /**
     * Give the game a webhook in its own announcements channel, so nobody has
     * to create one by hand and paste it in.
     *
     * A webhook the application already made is verified rather than replaced,
     * and the game's URL is only written when the webhook is created. If
     * Control has since repointed the game at a different channel, that is a
     * deliberate choice and re-provisioning must not undo it.
     */
    private function ensureAnnouncementWebhook(Game $game, string $reason): void
    {
        $channel = $this->recorded($game)->get(GuildBlueprint::CHANNEL_ANNOUNCEMENTS);

        if ($channel === null) {
            return;
        }

        $existing = DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('key', 'webhook:announcements')
            ->first();

        if ($existing !== null) {
            $stillThere = collect($this->api->channelWebhooks($channel->discord_id))
                ->contains(fn (array $webhook): bool => (string) $webhook['id'] === $existing->discord_id);

            if ($stillThere) {
                return;
            }
        }

        $webhook = $this->api->createWebhook($channel->discord_id, 'Running Hot', $reason);

        $this->record($game, DiscordResourceKind::Webhook, 'webhook:announcements', (string) $webhook['id'], 'Running Hot');

        if (filled($webhook['url'] ?? null)) {
            $game->update(['discord_webhook_url' => $webhook['url']]);
        }
    }

    /**
     * A permanent invite, so a player who signs in before joining the server
     * has somewhere to be sent. Control may override the link.
     */
    private function ensureInvite(Game $game, string $reason): void
    {
        if (filled($game->discord_invite_url)) {
            return;
        }

        $channel = $this->recorded($game)->get(GuildBlueprint::CHANNEL_ANNOUNCEMENTS);

        if ($channel === null) {
            return;
        }

        try {
            $invite = $this->api->createInvite($channel->discord_id, $reason);
        } catch (DiscordApiException $exception) {
            // An invite is a convenience, not the point of provisioning: a bot
            // without Create Instant Invite should still get a working server.
            Log::warning('Could not create a Discord invite.', [
                'game_id' => $game->id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if (filled($invite['code'] ?? null)) {
            $game->update(['discord_invite_url' => 'https://discord.gg/'.$invite['code']]);
        }
    }

    /**
     * Explain a 403 on a channel rather than repeating Discord's two words.
     *
     * The bot holds the Control role by this point, so it can see every channel
     * this application creates. A refusal therefore means somebody has taken
     * Control's access away from that channel by hand, or the bot has lost
     * Manage Channels in the guild.
     *
     * @template T of array<mixed>
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function lockoutAware(PlannedChannel $planned, callable $call): array
    {
        try {
            return $call();
        } catch (DiscordApiException $exception) {
            if (! $exception->isForbidden()) {
                throw $exception;
            }

            throw new DiscordApiException(
                sprintf(
                    'Discord refused to manage "%s" (Missing Permissions). Check that the Control role can '
                    .'still see that channel and its category, and that the bot still has Manage Channels '
                    .'and Manage Roles in the server.',
                    $planned->name,
                ),
                $exception->status,
                $exception->discordCode,
            );
        }
    }

    /**
     * Index Discord objects by snowflake.
     *
     * The prefix matters: a bare snowflake is a numeric string, and PHP would
     * silently coerce it to an integer array key. Keeping the key non-numeric
     * means a lookup can never miss because of how it was spelled.
     *
     * @param  array<int, array<string, mixed>>  $objects
     * @return array<string, array<string, mixed>>
     */
    private function bySnowflake(array $objects): array
    {
        $indexed = [];

        foreach ($objects as $object) {
            $indexed[self::snowflakeKey((string) $object['id'])] = $object;
        }

        return $indexed;
    }

    private static function snowflakeKey(string $snowflake): string
    {
        return 'id:'.$snowflake;
    }

    /**
     * @return Collection<string, DiscordResource>
     */
    private function recorded(Game $game, ?DiscordResourceKind $kind = null)
    {
        return DiscordResource::query()
            ->where('game_id', $game->id)
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind))
            ->get()
            ->keyBy('key');
    }

    private function record(Game $game, DiscordResourceKind $kind, string $key, string $discordId, string $name): void
    {
        DiscordResource::query()->updateOrCreate(
            ['game_id' => $game->id, 'key' => $key],
            ['kind' => $kind, 'discord_id' => $discordId, 'name' => $name],
        );
    }

    public static function markStatus(Game $game, DiscordProvisionStatus $status, ?string $message = null): void
    {
        $game->update([
            'discord_provision_status' => $status,
            'discord_provision_message' => $message,
            'discord_provisioned_at' => $status === DiscordProvisionStatus::Completed ? now() : $game->discord_provisioned_at,
        ]);
    }
}
