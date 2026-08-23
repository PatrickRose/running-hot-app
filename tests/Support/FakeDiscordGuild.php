<?php

namespace Tests\Support;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * An in-memory stand-in for a Discord guild.
 *
 * Faking the Discord API with fixed responses does not test the interesting
 * part of provisioning, which is that it reconciles: the second run must patch
 * what the first run created, and rebuild anything that has since vanished.
 * This keeps enough state to answer GETs with what previous POSTs did, so a
 * test can provision twice and assert on the difference.
 */
class FakeDiscordGuild
{
    /** @var array<string, array<string, mixed>> */
    public array $roles = [];

    /** @var array<string, array<string, mixed>> */
    public array $channels = [];

    /** @var array<string, array<string, mixed>> */
    public array $webhooks = [];

    /** @var array<string, array<int, string>> member snowflake to role snowflakes */
    public array $members = [];

    /**
     * What GET /users/@me/guilds answers with — the servers the bot is in.
     * Separate from this fake's own guild, so a test can describe a bot that is
     * in several servers, or none.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $botGuilds = [];

    /** @var array<int, array{method: string, url: string}> */
    public array $calls = [];

    private int $nextId = 1000000000000000000;

    public function __construct(public readonly string $guildId = '900000000000000001')
    {
        // Every guild has a default role sharing the guild's own snowflake.
        $this->roles[$this->guildId] = [
            'id' => $this->guildId,
            'name' => '@everyone',
            'color' => 0,
            'hoist' => false,
            'mentionable' => false,
        ];
    }

    /**
     * Register the fake and set a bot token, so the API client considers itself
     * configured. Returns $this for chaining in a test's arrange step.
     *
     * The client factory is replaced rather than added to: stub callbacks are
     * first-match-wins, and TestCase has already registered a catch-all that
     * would answer every Discord call with an empty 200. preventStrayRequests
     * is re-armed immediately, so an unmatched request still fails loudly.
     */
    public function bind(): self
    {
        config()->set('services.discord.bot_token', 'test-bot-token');

        Http::swap(new Factory(app('events')));
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->handle($request));

        return $this;
    }

    /**
     * Put a member in the guild, optionally already holding some roles.
     *
     * @param  array<int, string>  $roleIds
     */
    public function addMember(string $userId, array $roleIds = []): self
    {
        $this->members[$userId] = $roleIds;

        return $this;
    }

    public function roleNamed(string $name): ?array
    {
        foreach ($this->roles as $role) {
            if ($role['name'] === $name) {
                return $role;
            }
        }

        return null;
    }

    public function channelNamed(string $name): ?array
    {
        foreach ($this->channels as $channel) {
            if ($channel['name'] === $name) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function childrenOf(string $categoryId): array
    {
        return array_values(array_filter(
            $this->channels,
            fn (array $channel): bool => ($channel['parent_id'] ?? null) === $categoryId,
        ));
    }

    public function countCalls(string $method, string $needle): int
    {
        return count(array_filter(
            $this->calls,
            fn (array $call): bool => $call['method'] === mb_strtoupper($method) && str_contains($call['url'], $needle),
        ));
    }

    /**
     * Route one request to the piece of guild state it acts on.
     */
    private function handle(Request $request): Response|PromiseInterface
    {
        $method = mb_strtoupper($request->method());
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
        $path = (string) preg_replace('#^/api/v\d+#', '', $path);
        $body = $request->data();

        $this->calls[] = ['method' => $method, 'url' => $request->url()];

        // Guild member roles: PUT/DELETE /guilds/{g}/members/{u}/roles/{r}
        if ($path === '/users/@me/guilds') {
            return Http::response($this->botGuilds);
        }

        if (preg_match('#^/guilds/(\d+)/members/(\d+)/roles/(\d+)$#', $path, $matches) === 1) {
            [, , $userId, $roleId] = $matches;

            if ($method === 'PUT') {
                $this->members[$userId] = array_values(array_unique([...($this->members[$userId] ?? []), $roleId]));
            } else {
                $this->members[$userId] = array_values(array_diff($this->members[$userId] ?? [], [$roleId]));
            }

            return Http::response(null, 204);
        }

        if (preg_match('#^/guilds/(\d+)/members/(\d+)$#', $path, $matches) === 1) {
            $userId = $matches[2];

            if (! array_key_exists($userId, $this->members)) {
                return Http::response(['message' => 'Unknown Member', 'code' => 10007], 404);
            }

            return Http::response(['user' => ['id' => $userId], 'roles' => $this->members[$userId]]);
        }

        if (preg_match('#^/guilds/(\d+)/roles$#', $path) === 1) {
            if ($method === 'POST') {
                $role = [
                    'id' => (string) $this->nextId++,
                    'name' => $body['name'],
                    'color' => $body['color'],
                    'hoist' => $body['hoist'],
                    'mentionable' => $body['mentionable'],
                ];
                $this->roles[$role['id']] = $role;

                return Http::response($role, 201);
            }

            return Http::response(array_values($this->roles));
        }

        if (preg_match('#^/guilds/(\d+)/roles/(\d+)$#', $path, $matches) === 1) {
            $roleId = $matches[2];
            $this->roles[$roleId] = [...$this->roles[$roleId], ...array_intersect_key($body, array_flip(['name', 'color', 'hoist', 'mentionable']))];

            return Http::response($this->roles[$roleId]);
        }

        if (preg_match('#^/guilds/(\d+)/channels$#', $path) === 1) {
            if ($method === 'POST') {
                $channel = [
                    'id' => (string) $this->nextId++,
                    'name' => $body['name'],
                    'type' => $body['type'],
                    'parent_id' => $body['parent_id'] ?? null,
                    'topic' => $body['topic'] ?? null,
                    'permission_overwrites' => $body['permission_overwrites'] ?? [],
                ];
                $this->channels[$channel['id']] = $channel;

                return Http::response($channel, 201);
            }

            return Http::response(array_values($this->channels));
        }

        if (preg_match('#^/channels/(\d+)/webhooks$#', $path, $matches) === 1) {
            $channelId = $matches[1];

            if ($method === 'POST') {
                $id = (string) $this->nextId++;
                $webhook = [
                    'id' => $id,
                    'channel_id' => $channelId,
                    'name' => $body['name'],
                    'url' => 'https://discord.com/api/webhooks/'.$id.'/'.str_repeat('t', 24),
                ];
                $this->webhooks[$id] = $webhook;

                return Http::response($webhook, 200);
            }

            return Http::response(array_values(array_filter(
                $this->webhooks,
                fn (array $webhook): bool => $webhook['channel_id'] === $channelId,
            )));
        }

        if (preg_match('#^/channels/(\d+)/invites$#', $path) === 1) {
            return Http::response(['code' => 'runninghot']);
        }

        if (preg_match('#^/channels/(\d+)$#', $path, $matches) === 1) {
            $channelId = $matches[1];

            if (! isset($this->channels[$channelId])) {
                return Http::response(['message' => 'Unknown Channel', 'code' => 10003], 404);
            }

            $this->channels[$channelId] = [...$this->channels[$channelId], ...$body];

            return Http::response($this->channels[$channelId]);
        }

        if (preg_match('#^/guilds/(\d+)$#', $path, $matches) === 1) {
            if ($matches[1] !== $this->guildId) {
                return Http::response(['message' => 'Unknown Guild', 'code' => 10004], 404);
            }

            return Http::response(['id' => $this->guildId, 'name' => 'Running Hot']);
        }

        // Announcement webhook posts and anything else: harmless, and letting
        // them through keeps this fake focused on the bot API.
        return Http::response([], 204);
    }
}
