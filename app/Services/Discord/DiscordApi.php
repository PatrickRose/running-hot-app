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

    public function isConfigured(): bool
    {
        return filled(config('services.discord.bot_token'));
    }

    /**
     * @return array<string, mixed>
     */
    public function guild(string $guildId): array
    {
        return $this->get("/guilds/{$guildId}");
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
