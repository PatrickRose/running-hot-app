<?php

namespace App\Actions;

use App\Enums\DiscordProvisionStatus;
use App\Enums\DiscordResourceKind;
use App\Models\DiscordResource;
use App\Models\Game;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordApiException;
use App\Services\Discord\DiscordNotConfiguredException;
use App\Support\Discord\ChannelPayload;
use App\Support\Discord\GuildBlueprint;
use App\Support\Discord\PlannedChannel;
use App\Support\Discord\PlannedRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Brings a game's Discord guild in line with its blueprint.
 *
 * Reconciles rather than resets. Every object the application owns is recorded
 * in `discord_resources` against a stable key, so a re-run renames what
 * drifted, rebuilds what somebody deleted by hand, and adds whatever the roster
 * has grown since.
 *
 * A key with no record is looked for in the guild by name before it is created.
 * Without that, a guild the application has provisioned before but has no
 * record of — a database rebuilt, a game recreated, a server set up by hand
 * against the blueprint — grows a second Control role and a second category
 * per team every single run. Adopting is the reconcile the recorded key would
 * have done, on the evidence available. Anything the blueprint does not ask
 * for is still never touched: Control's own channels are Control's business,
 * and a mid-game run must be safe.
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

        $this->api->assertBotIsInGuild($guildId);

        $tally = [
            'roles_created' => 0, 'roles_adopted' => 0, 'roles_updated' => 0,
            'channels_created' => 0, 'channels_adopted' => 0, 'channels_updated' => 0,
        ];

        $roleIds = $this->reconcileRoles($game, $guildId, $blueprint, $reason, $tally);
        $this->giveBotTheControlRole($guildId, $roleIds, $reason);
        $this->reconcileChannels($game, $guildId, $blueprint, $roleIds, $reason, $tally);
        $this->ensureAnnouncementWebhook($game, $reason);
        $this->ensureInvite($game, $reason);

        Log::info('Discord guild provisioned.', ['game_id' => $game->id, 'guild_id' => $guildId, ...$tally]);

        return $tally;
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
        $claimed = $this->claimedSnowflakes($game);

        $roleIds = [];

        foreach ($blueprint->roles() as $key => $planned) {
            $resource = $recorded->get($key);
            $existing = $resource === null ? null : ($live[self::snowflakeKey($resource->discord_id)] ?? null);

            if ($existing === null) {
                // No record, or the recorded one has been deleted in Discord.
                // Before making another, see whether the guild already has the
                // role this key describes.
                $existing = $this->roleToAdopt($live, $claimed, $planned, $guildId);

                if ($existing === null) {
                    $created = $this->api->createRole($guildId, $planned->payload(), $reason);
                    $this->claim($claimed, (string) $created['id']);
                    $this->record($game, DiscordResourceKind::Role, $key, (string) $created['id'], $planned->name);
                    $roleIds[$key] = (string) $created['id'];
                    $tally['roles_created']++;

                    continue;
                }

                $this->claim($claimed, (string) $existing['id']);
                $resource = $this->record($game, DiscordResourceKind::Role, $key, (string) $existing['id'], $planned->name);
                $tally['roles_adopted']++;
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
     * The role already in the guild that this key describes, if there is one.
     *
     * Name is all there is to go on, so the match is on name alone, ignoring
     * case because Discord does not. Two things are never candidates: the
     * guild's default role, which is @everyone under a snowflake that happens
     * to equal the guild's, and a managed role, which belongs to an integration
     * and cannot be renamed or given to anybody anyway.
     *
     * @param  array<string, array<string, mixed>>  $live
     * @param  array<string, true>  $claimed
     * @return array<string, mixed>|null
     */
    private function roleToAdopt(array $live, array $claimed, PlannedRole $planned, string $guildId): ?array
    {
        foreach ($live as $snowflakeKey => $role) {
            $id = (string) $role['id'];

            if ($id === $guildId || ($role['managed'] ?? false) === true) {
                continue;
            }

            if (isset($claimed[$snowflakeKey])) {
                continue;
            }

            if ($this->sameName((string) ($role['name'] ?? ''), $planned->name)) {
                return $role;
            }
        }

        return null;
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
        $claimed = $this->claimedSnowflakes($game);

        // Snowflakes of the categories created in this pass, so a channel can
        // be parented to one made moments ago.
        $channelIds = [];

        foreach ($blueprint->channels() as $planned) {
            $resource = $recorded->get($planned->key);
            $existing = $resource === null ? null : ($live[self::snowflakeKey($resource->discord_id)] ?? null);

            $payload = $this->channelPayload($planned, $guildId, $roleIds, $channelIds);

            if ($existing === null) {
                $existing = $this->channelToAdopt($live, $claimed, $planned, $channelIds);

                if ($existing === null) {
                    $created = $this->lockoutAware(
                        $planned,
                        fn (): array => $this->api->createChannel($guildId, $payload, $reason),
                    );
                    $this->claim($claimed, (string) $created['id']);
                    $this->record($game, $planned->kind, $planned->key, (string) $created['id'], $planned->name);
                    $channelIds[$planned->key] = (string) $created['id'];
                    $tally['channels_created']++;

                    continue;
                }

                $this->claim($claimed, (string) $existing['id']);
                $resource = $this->record($game, $planned->kind, $planned->key, (string) $existing['id'], $planned->name);
                $tally['channels_adopted']++;
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
     * The channel already in the guild that this key describes, if there is one.
     *
     * Stricter than the role match, because a channel name on its own is not
     * distinctive: a corporation's category and its text channel are both named
     * for the corporation, and two teams could each have a "voice". So the type
     * and the parent category have to line up as well, which is exactly the
     * triple that makes a channel unique to a player looking at the sidebar.
     *
     * A planned child whose category was only just created cannot match
     * anything — nothing can already be parented to a category that did not
     * exist a moment ago — so those fall through to being created, which is
     * right.
     *
     * @param  array<string, array<string, mixed>>  $live
     * @param  array<string, true>  $claimed
     * @param  array<string, string>  $channelIds
     * @return array<string, mixed>|null
     */
    private function channelToAdopt(array $live, array $claimed, PlannedChannel $planned, array $channelIds): ?array
    {
        if ($planned->parentKey === null) {
            $wantedParent = null;
        } elseif (isset($channelIds[$planned->parentKey])) {
            $wantedParent = $channelIds[$planned->parentKey];
        } else {
            // The parent could not be resolved. Matching on a null parent here
            // would adopt some unrelated top-level channel.
            return null;
        }

        foreach ($live as $snowflakeKey => $channel) {
            if (isset($claimed[$snowflakeKey])) {
                continue;
            }

            if ((int) ($channel['type'] ?? -1) !== $planned->kind->channelType()) {
                continue;
            }

            $parentId = $channel['parent_id'] ?? null;

            if (($parentId === null ? null : (string) $parentId) !== $wantedParent) {
                continue;
            }

            if ($this->sameName((string) ($channel['name'] ?? ''), $planned->name)) {
                return $channel;
            }
        }

        return null;
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
        return ChannelPayload::for($planned, $guildId, $roleIds, $channelIds);
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

        $live = collect($this->api->channelWebhooks($channel->discord_id));

        if ($existing !== null
            && $live->contains(fn (array $webhook): bool => (string) $webhook['id'] === $existing->discord_id)) {
            return;
        }

        // Same reasoning as roles and channels: a channel that already has this
        // application's webhook in it should not collect a second one because
        // the record of the first has been lost.
        $adopted = $live->first(fn (array $webhook): bool => $this->sameName((string) ($webhook['name'] ?? ''), 'Running Hot'));

        if (is_array($adopted)) {
            $this->record($game, DiscordResourceKind::Webhook, 'webhook:announcements', (string) $adopted['id'], 'Running Hot');

            // Listing webhooks as the bot gives back the token rather than the
            // ready-made URL, so the URL is rebuilt from it. Only ever written
            // when the game has none: a game Control has pointed somewhere else
            // stays pointed there, exactly as for a webhook we made ourselves.
            $url = $adopted['url'] ?? (filled($adopted['token'] ?? null)
                ? sprintf('https://discord.com/api/webhooks/%s/%s', $adopted['id'], $adopted['token'])
                : null);

            if (blank($game->discord_webhook_url) && filled($url)) {
                $game->update(['discord_webhook_url' => $url]);
            }

            return;
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

    private function record(Game $game, DiscordResourceKind $kind, string $key, string $discordId, string $name): DiscordResource
    {
        return DiscordResource::query()->updateOrCreate(
            ['game_id' => $game->id, 'key' => $key],
            ['kind' => $kind, 'discord_id' => $discordId, 'name' => $name],
        );
    }

    /**
     * Every snowflake this game has already spoken for.
     *
     * Adoption matches on a name, and names repeat: without this, a
     * corporation's category and its voice channel — which share a name — would
     * both adopt the same object, and the second key would then be recorded
     * against a snowflake the first one is also using.
     *
     * @return array<string, true>
     */
    private function claimedSnowflakes(Game $game): array
    {
        $claimed = [];

        foreach ($this->recorded($game) as $resource) {
            $claimed[self::snowflakeKey($resource->discord_id)] = true;
        }

        return $claimed;
    }

    /**
     * @param  array<string, true>  $claimed
     */
    private function claim(array &$claimed, string $discordId): void
    {
        $claimed[self::snowflakeKey($discordId)] = true;
    }

    /**
     * Discord lower-cases a text channel's name for you and is case-insensitive
     * about collisions, so a match that cared about case would miss the very
     * objects adoption exists to find.
     */
    private function sameName(string $actual, string $planned): bool
    {
        return mb_strtolower(trim($actual)) === mb_strtolower(trim($planned));
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
