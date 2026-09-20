<?php

namespace Tests\Support;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
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

    /**
     * Messages per channel, oldest first.
     *
     * Kept as state for the reason the permission overwrites are: the
     * interesting question about clearing a run channel is what is *left* in it
     * afterwards, and a pinned message is supposed to be.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    public array $messages = [];

    /**
     * Whether to behave like a Discord that refuses the bot's grants.
     *
     * Discord applies only the overwrite bits the caller holds itself, so a bot
     * short of one of them gets a channel back carrying its denials and none of
     * its grants - no error, just a lock nobody holds the key to. Set this and
     * the fake answers the same way.
     */
    public bool $dropsGrantsTheBotCannotMake = false;

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

    /** The snowflake GET /users/@me answers with — the bot's own account. */
    public string $botUserId = '800000000000000001';

    /**
     * Make role assignment fail with 403, as Discord does when the role sits
     * above the assigner's own in the guild's hierarchy.
     */
    public bool $refuseRoleGrants = false;

    /**
     * Role names whose deletion answers 403, as Discord does for a role sitting
     * above the bot's own in the guild's hierarchy. Every real guild has at
     * least one, so a wipe has to cope with it.
     *
     * @var array<int, string>
     */
    public array $refuseRoleDeletes = [];

    public function __construct(public readonly string $guildId = '900000000000000001')
    {
        $this->members[$this->botUserId] = [];

        // Every guild has a default role sharing the guild's own snowflake.
        $this->roles[$this->guildId] = [
            'id' => $this->guildId,
            'name' => '@everyone',
            'color' => 0,
            'hoist' => false,
            'mentionable' => false,
            'managed' => false,
        ];
    }

    /**
     * Put a role in the guild that the application did not make.
     *
     * How a test describes the thing this fake exists to catch: a server
     * somebody has already provisioned, or set up by hand, that the database
     * knows nothing about.
     *
     * @param  array<string, mixed>  $attributes
     * @return string the new role's snowflake
     */
    public function seedRole(string $name, array $attributes = []): string
    {
        $id = (string) $this->nextId++;

        $this->roles[$id] = [
            'id' => $id,
            'name' => $name,
            'color' => 0,
            'hoist' => false,
            'mentionable' => false,
            'managed' => false,
            ...$attributes,
        ];

        return $id;
    }

    /**
     * Put a channel or category in the guild that the application did not make.
     *
     * @return string the new channel's snowflake
     */
    public function seedChannel(string $name, int $type, ?string $parentId = null): string
    {
        $id = (string) $this->nextId++;

        $this->channels[$id] = [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'parent_id' => $parentId,
            'topic' => null,
            'permission_overwrites' => [],
        ];

        return $id;
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

    /**
     * Put a message in a channel, as a player or the bot would have.
     *
     * @return string the message's snowflake
     */
    public function postMessage(
        string $channelId,
        string $content = 'something said during a run',
        bool $pinned = false,
        ?string $timestamp = null,
    ): string {
        $id = (string) $this->nextId++;

        $this->messages[$channelId][] = [
            'id' => $id,
            'content' => $content,
            'pinned' => $pinned,
            'timestamp' => $timestamp ?? now()->toIso8601String(),
        ];

        return $id;
    }

    /**
     * What is still in a channel, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messagesIn(string $channelId): array
    {
        return array_values($this->messages[$channelId] ?? []);
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

    /**
     * The overwrites Discord would keep out of the ones it was sent.
     *
     * @param  array<int, array<string, mixed>>  $overwrites
     * @return array<int, array<string, mixed>>
     */
    private function applyOverwrites(array $overwrites): array
    {
        if (! $this->dropsGrantsTheBotCannotMake) {
            return $overwrites;
        }

        return array_values(array_filter(
            $overwrites,
            fn (array $overwrite): bool => (int) ($overwrite['allow'] ?? 0) === 0,
        ));
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
        if ($path === '/users/@me') {
            return Http::response(['id' => $this->botUserId, 'username' => 'running-hot-bot', 'bot' => true]);
        }

        if ($path === '/users/@me/guilds') {
            return Http::response($this->botGuilds);
        }

        if (preg_match('#^/guilds/(\d+)/members/(\d+)/roles/(\d+)$#', $path, $matches) === 1) {
            [, , $userId, $roleId] = $matches;

            if ($method === 'PUT') {
                if ($this->refuseRoleGrants) {
                    return Http::response(['message' => 'Missing Permissions', 'code' => 50013], 403);
                }

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
                    'managed' => false,
                ];
                $this->roles[$role['id']] = $role;

                return Http::response($role, 201);
            }

            return Http::response(array_values($this->roles));
        }

        if (preg_match('#^/guilds/(\d+)/roles/(\d+)$#', $path, $matches) === 1) {
            $roleId = $matches[2];

            if (! isset($this->roles[$roleId])) {
                return Http::response(['message' => 'Unknown Role', 'code' => 10011], 404);
            }

            if ($method === 'DELETE') {
                // Discord refuses to delete a role belonging to an integration,
                // and the default role is not a thing that can be deleted.
                if ($roleId === $this->guildId
                    || ($this->roles[$roleId]['managed'] ?? false) === true
                    || in_array($this->roles[$roleId]['name'], $this->refuseRoleDeletes, true)) {
                    return Http::response(['message' => 'Missing Permissions', 'code' => 50013], 403);
                }

                unset($this->roles[$roleId]);

                return Http::response(null, 204);
            }

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
                    'permission_overwrites' => $this->applyOverwrites($body['permission_overwrites'] ?? []),
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

        // One line of a channel's permission table:
        // PUT/DELETE /channels/{c}/permissions/{overwrite}
        //
        // Kept as state rather than answered blankly, because the interesting
        // question is what the channel's overwrites *are* afterwards - a run
        // grants one per Runner and takes it away again, and a provision run
        // in between must not carry them off.
        if (preg_match('#^/channels/(\d+)/permissions/(\d+)$#', $path, $matches) === 1) {
            [, $channelId, $overwriteId] = $matches;

            if (! isset($this->channels[$channelId])) {
                return Http::response(['message' => 'Unknown Channel', 'code' => 10003], 404);
            }

            $overwrites = array_values(array_filter(
                $this->channels[$channelId]['permission_overwrites'] ?? [],
                fn (array $overwrite): bool => ($overwrite['id'] ?? null) !== $overwriteId,
            ));

            if ($method === 'PUT') {
                // An upsert: Discord replaces whatever that id already had.
                $overwrites[] = [
                    'id' => $overwriteId,
                    'type' => (int) ($body['type'] ?? 0),
                    'allow' => (string) ($body['allow'] ?? '0'),
                    'deny' => (string) ($body['deny'] ?? '0'),
                ];
            }

            // Removing an overwrite that was never there is not an error, which
            // is what lets a run be revoked without having recorded whether it
            // was ever granted.
            $this->channels[$channelId]['permission_overwrites'] = $overwrites;

            return Http::response(null, 204);
        }

        // Deleting up to 100 at once. Discord refuses a batch of one outright,
        // and refuses the whole batch if anything in it is over two weeks old -
        // both of which a caller clearing a channel has to work around, so the
        // fake refuses them too.
        if (preg_match('#^/channels/(\d+)/messages/bulk-delete$#', $path, $matches) === 1) {
            $channelId = $matches[1];
            $ids = $body['messages'] ?? [];

            if (count($ids) < 2 || count($ids) > 100) {
                return Http::response(['message' => 'Invalid Form Body', 'code' => 50035], 400);
            }

            $fortnightAgo = now()->subDays(14);

            foreach ($this->messages[$channelId] ?? [] as $message) {
                if (in_array($message['id'], $ids, true)
                    && Carbon::parse($message['timestamp'])->lessThan($fortnightAgo)) {
                    return Http::response(
                        ['message' => 'You can only bulk delete messages that are under 14 days old.', 'code' => 50034],
                        400,
                    );
                }
            }

            $this->messages[$channelId] = array_values(array_filter(
                $this->messages[$channelId] ?? [],
                fn (array $message): bool => ! in_array($message['id'], $ids, true),
            ));

            return Http::response(null, 204);
        }

        if (preg_match('#^/channels/(\d+)/messages/(\d+)$#', $path, $matches) === 1) {
            [, $channelId, $messageId] = $matches;

            if ($method === 'DELETE') {
                $this->messages[$channelId] = array_values(array_filter(
                    $this->messages[$channelId] ?? [],
                    fn (array $message): bool => $message['id'] !== $messageId,
                ));

                return Http::response(null, 204);
            }

            return Http::response(['id' => $messageId]);
        }

        if (preg_match('#^/channels/(\d+)/messages$#', $path, $matches) === 1) {
            $channelId = $matches[1];

            if ($method === 'POST') {
                $id = $this->postMessage($channelId, (string) ($body['content'] ?? ''));

                return Http::response(['id' => $id, 'channel_id' => $channelId], 200);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $limit = (int) ($query['limit'] ?? 50);
            $before = $query['before'] ?? null;

            // Newest first, which is the order Discord answers in.
            $messages = array_reverse($this->messagesIn($channelId));

            if (is_string($before) && $before !== '') {
                $messages = array_values(array_filter(
                    $messages,
                    // Snowflakes fit in a 64-bit int, and paging by one is a
                    // numeric comparison rather than a string one.
                    fn (array $message): bool => (int) $message['id'] < (int) $before,
                ));
            }

            return Http::response(array_slice($messages, 0, max(1, min(100, $limit))));
        }

        if (preg_match('#^/channels/(\d+)$#', $path, $matches) === 1) {
            $channelId = $matches[1];

            if (! isset($this->channels[$channelId])) {
                return Http::response(['message' => 'Unknown Channel', 'code' => 10003], 404);
            }

            if ($method === 'DELETE') {
                $deleted = $this->channels[$channelId];
                unset($this->channels[$channelId]);

                // Deleting a category orphans its children at the top level
                // rather than taking them with it, which is the behaviour a
                // caller clearing a guild has to work around.
                foreach ($this->channels as $id => $channel) {
                    if (($channel['parent_id'] ?? null) === $channelId) {
                        $this->channels[$id]['parent_id'] = null;
                    }
                }

                // A webhook does not outlive the channel it posts to.
                $this->webhooks = array_filter(
                    $this->webhooks,
                    fn (array $webhook): bool => $webhook['channel_id'] !== $channelId,
                );

                return Http::response($deleted);
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
