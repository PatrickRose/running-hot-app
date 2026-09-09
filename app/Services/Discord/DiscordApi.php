<?php

namespace App\Services\Discord;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A thin client over the parts of the Discord REST API the game needs.
 *
 * Only the bot-token half of the Discord integration lives here. OAuth is
 * Socialite's job and announcements go through an incoming webhook, neither of
 * which needs a bot.
 *
 * Rate limits are the thing to know about this API: role and channel creation
 * are limited hard, and Discord answers with 429 plus a `retry_after` in
 * seconds. Retries are therefore built in at this level, so callers can write
 * straight-line provisioning code.
 */
class DiscordApi
{
    /**
     * Discord permission bits used when locking channels down.
     *
     * @see https://discord.com/developers/docs/topics/permissions
     */
    public const VIEW_CHANNEL = 1 << 10;

    public const SEND_MESSAGES = 1 << 11;

    public const CONNECT = 1 << 20;

    public const SPEAK = 1 << 21;

    /** Permission overwrite targets. */
    public const OVERWRITE_ROLE = 0;

    /**
     * An overwrite naming one member rather than a role.
     *
     * Used for the Runners on a run: they are let into the Facility's channels
     * for the duration of it and taken out again afterwards, which is a thing
     * that happens to those people rather than to any role in the guild.
     */
    public const OVERWRITE_MEMBER = 1;

    /**
     * What the bot must be granted in a guild to provision it.
     *
     * Create Instant Invite is for the join link handed to players who have not
     * joined yet; the rest are for building the server out. Deliberately no
     * more than that: a bot that cannot kick, ban or read messages is an easier
     * thing to invite to a server full of players.
     */
    public const BOT_PERMISSIONS = 1        // Create Instant Invite
        | (1 << 4)                          // Manage Channels
        | (1 << 28)                         // Manage Roles
        | (1 << 29);                        // Manage Webhooks

    public function isConfigured(): bool
    {
        return filled(config('services.discord.bot_token'));
    }

    /**
     * The bot's own account, whose snowflake is needed to give it a permission
     * overwrite on the channels it locks down.
     *
     * @return array<string, mixed>
     */
    public function currentUser(): array
    {
        return $this->get('/users/@me');
    }

    /**
     * Every guild this bot is a member of.
     *
     * How Control picks a server without copying a snowflake: the bot can only
     * see guilds it has been added to, so the list is exactly the set of valid
     * choices.
     *
     * @return array<int, array<string, mixed>>
     */
    public function botGuilds(): array
    {
        return $this->get('/users/@me/guilds');
    }

    /**
     * @return array<string, mixed>
     */
    public function guild(string $guildId): array
    {
        return $this->get("/guilds/{$guildId}");
    }

    /**
     * Fail fast, and in words Control can act on, if the bot is not in a guild.
     *
     * This is by far the most common setup mistake, and Discord's own answer —
     * a bare "Unknown Guild" 404 — reads like the server does not exist. With a
     * token that authenticates (an invalid one is a 401), a 404 here means only
     * one thing: this bot is not a member. Saying so, and pointing at the
     * invite, saves the guess.
     */
    public function assertBotIsInGuild(string $guildId): void
    {
        try {
            $this->guild($guildId);
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
     * @return array<int, array<string, mixed>>
     */
    public function roles(string $guildId): array
    {
        return $this->get("/guilds/{$guildId}/roles");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRole(string $guildId, array $payload, ?string $reason = null): array
    {
        return $this->post("/guilds/{$guildId}/roles", $payload, $reason);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateRole(string $guildId, string $roleId, array $payload, ?string $reason = null): array
    {
        return $this->patch("/guilds/{$guildId}/roles/{$roleId}", $payload, $reason);
    }

    /**
     * Remove a role from the guild entirely.
     *
     * Only ever called by a deliberate reset. Discord refuses this for a
     * managed role (one owned by an integration) and for any role sitting
     * above the bot's own, so callers must expect a 403 and carry on.
     */
    public function deleteRole(string $guildId, string $roleId, ?string $reason = null): void
    {
        $this->send('delete', "/guilds/{$guildId}/roles/{$roleId}", null, $reason);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function channels(string $guildId): array
    {
        return $this->get("/guilds/{$guildId}/channels");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createChannel(string $guildId, array $payload, ?string $reason = null): array
    {
        return $this->post("/guilds/{$guildId}/channels", $payload, $reason);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateChannel(string $channelId, array $payload, ?string $reason = null): array
    {
        return $this->patch("/channels/{$channelId}", $payload, $reason);
    }

    /**
     * Delete a channel or category, and with it every message in it.
     *
     * Deleting a category does not delete the channels inside it: Discord
     * leaves them behind at the top level, so a caller clearing a guild has to
     * take the children first.
     */
    public function deleteChannel(string $channelId, ?string $reason = null): void
    {
        $this->send('delete', "/channels/{$channelId}", null, $reason);
    }

    /**
     * Let one member (or role) into a channel, or change what they may do in it.
     *
     * An upsert: Discord replaces whatever overwrite that id already had, so
     * this is safe to repeat and there is no "already granted" to check for.
     *
     * @param  array<string, mixed>  $payload
     */
    public function setChannelPermission(
        string $channelId,
        string $overwriteId,
        array $payload,
        ?string $reason = null,
    ): void {
        $this->send('put', "/channels/{$channelId}/permissions/{$overwriteId}", $payload, $reason);
    }

    /**
     * Take an overwrite off a channel.
     *
     * Removing one that was never there is not an error, which is what makes it
     * safe to revoke a run's access without having recorded whether it was
     * granted.
     */
    public function deleteChannelPermission(
        string $channelId,
        string $overwriteId,
        ?string $reason = null,
    ): void {
        $this->send('delete', "/channels/{$channelId}/permissions/{$overwriteId}", null, $reason);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function channelWebhooks(string $channelId): array
    {
        return $this->get("/channels/{$channelId}/webhooks");
    }

    /**
     * @return array<string, mixed>
     */
    public function createWebhook(string $channelId, string $name, ?string $reason = null): array
    {
        return $this->post("/channels/{$channelId}/webhooks", ['name' => $name], $reason);
    }

    /**
     * @return array<string, mixed>
     */
    public function createInvite(string $channelId, ?string $reason = null): array
    {
        // A permanent, unlimited invite: players join once, on their own time,
        // and an expiry would only produce a dead link mid-game.
        return $this->post("/channels/{$channelId}/invites", [
            'max_age' => 0,
            'max_uses' => 0,
            'unique' => false,
        ], $reason);
    }

    /**
     * The player's membership of a guild, or null if they have not joined.
     *
     * @return array<string, mixed>|null
     */
    public function guildMember(string $guildId, string $userId): ?array
    {
        try {
            return $this->get("/guilds/{$guildId}/members/{$userId}");
        } catch (DiscordApiException $exception) {
            if ($exception->isNotFound()) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * Post a message to a channel as the bot.
     *
     * The announcement webhook cannot do this: a webhook only ever posts to the
     * channel it was created in. Anything that has to land in a particular
     * channel needs the bot.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createMessage(string $channelId, array $payload): array
    {
        /** @var array<string, mixed> */
        return $this->post("/channels/{$channelId}/messages", $payload);
    }

    /**
     * Rewrite a message the bot posted earlier.
     *
     * Used rather than posting again so that a list which changes during the
     * game stays in one place: a channel full of superseded lists is worse than
     * no list, because players would have to work out which one is current.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function editMessage(string $channelId, string $messageId, array $payload): array
    {
        /** @var array<string, mixed> */
        return $this->patch("/channels/{$channelId}/messages/{$messageId}", $payload);
    }

    public function addRoleToMember(string $guildId, string $userId, string $roleId, ?string $reason = null): void
    {
        $this->send('put', "/guilds/{$guildId}/members/{$userId}/roles/{$roleId}", null, $reason);
    }

    public function removeRoleFromMember(string $guildId, string $userId, string $roleId, ?string $reason = null): void
    {
        $this->send('delete', "/guilds/{$guildId}/members/{$userId}/roles/{$roleId}", null, $reason);
    }

    /**
     * @return array<mixed>
     */
    private function get(string $path): array
    {
        return $this->send('get', $path);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    private function post(string $path, array $payload, ?string $reason = null): array
    {
        return $this->send('post', $path, $payload, $reason);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    private function patch(string $path, array $payload, ?string $reason = null): array
    {
        return $this->send('patch', $path, $payload, $reason);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<mixed>
     */
    private function send(string $method, string $path, ?array $payload = null, ?string $reason = null): array
    {
        $response = $this->request($reason)->{$method}($this->url($path), $payload ?? []);

        if ($response->failed()) {
            throw $this->exceptionFor($response, $method, $path);
        }

        // Role removal and a few other calls answer 204 with an empty body.
        return $response->json() ?? [];
    }

    private function request(?string $reason = null): PendingRequest
    {
        $token = config('services.discord.bot_token');

        if (blank($token)) {
            throw new DiscordNotConfiguredException(
                'DISCORD_BOT_TOKEN is not set, so the application cannot talk to Discord as a bot.'
            );
        }

        $headers = [
            'Authorization' => 'Bot '.$token,
            // Discord asks bots to identify themselves, and it makes the
            // application obvious in a guild's audit log.
            'User-Agent' => 'RunningHot (https://github.com/PatrickRose/running-hot-app, 1.0)',
        ];

        if (filled($reason)) {
            // Shows up against every change in the guild's audit log, which is
            // how Control answers "who renamed that channel?".
            $headers['X-Audit-Log-Reason'] = mb_substr($reason, 0, 512);
        }

        return Http::asJson()
            ->withHeaders($headers)
            ->timeout(15)
            ->retry(
                times: 4,
                sleepMilliseconds: fn (int $attempt, Throwable $exception): int => $this->backoffFor($attempt, $exception),
                when: fn (Throwable $exception): bool => $this->shouldRetry($exception),
                throw: false,
            );
    }

    /**
     * Honour Discord's own `retry_after` when it gives one, and back off
     * exponentially when the failure is a transport error with no hint.
     */
    private function backoffFor(int $attempt, Throwable $exception): int
    {
        $response = $exception instanceof RequestException
            ? $exception->response
            : null;

        $retryAfter = $response?->json('retry_after');

        if (is_numeric($retryAfter)) {
            // Discord sends seconds as a float; add a little headroom so a
            // clock difference does not put the retry back inside the window.
            return (int) ceil(((float) $retryAfter * 1000) + 250);
        }

        return 500 * (2 ** ($attempt - 1));
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if (! $exception instanceof RequestException) {
            // A connection error: worth one more go.
            return true;
        }

        $status = $exception->response->status();

        // 429 is the rate limit and 5xx is Discord having a bad day. A 4xx of
        // any other kind means the request itself is wrong, so retrying it just
        // burns the rate limit budget.
        return $status === 429 || $status >= 500;
    }

    private function exceptionFor(Response $response, string $method, string $path): DiscordApiException
    {
        $body = $response->json();
        $message = is_array($body) && is_string($body['message'] ?? null)
            ? $body['message']
            : $response->body();

        return new DiscordApiException(
            sprintf(
                'Discord %s %s failed with %d: %s',
                mb_strtoupper($method),
                $path,
                $response->status(),
                mb_substr((string) $message, 0, 500),
            ),
            $response->status(),
            is_array($body) && is_int($body['code'] ?? null) ? $body['code'] : null,
        );
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.discord.api_base'), '/').$path;
    }
}
