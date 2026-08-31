<?php

namespace App\Http\Controllers\Control;

use App\Actions\ProvisionDiscordGuild;
use App\Enums\DiscordProvisionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\ResetDiscordGuildRequest;
use App\Http\Requests\Control\UpdateGameGuildRequest;
use App\Jobs\ProvisionDiscordGuildJob;
use App\Jobs\ResetDiscordGuildJob;
use App\Jobs\SyncDiscordRoles;
use App\Models\Game;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Control's half of the bot integration: which server a game lives in, and the
 * two buttons that push the application's idea of it into Discord.
 */
class DiscordGuildController extends Controller
{
    /**
     * Session key holding the pending bot-add, so the callback knows which game
     * the server it is being handed belongs to.
     */
    private const CONNECT_SESSION_KEY = 'discord.bot_connect';

    public function __construct(private readonly DiscordApi $api) {}

    /**
     * Send Control to Discord to add the bot to a server.
     *
     * This is the good path for attaching a server to a game: Discord shows its
     * own server picker, and on the way back it tells us which one was chosen,
     * so nobody has to turn on Developer Mode and copy a snowflake.
     */
    public function connect(Game $game, Request $request): SymfonyResponse
    {
        $clientId = config('services.discord.client_id');

        if (blank($clientId)) {
            return back()->withErrors([
                'discord_guild_id' => 'Set DISCORD_CLIENT_ID before adding the bot to a server.',
            ]);
        }

        $state = Str::random(40);

        $request->session()->put(self::CONNECT_SESSION_KEY, [
            'state' => $state,
            'game_id' => $game->id,
        ]);

        // Inertia::location rather than a plain redirect: an XHR cannot follow a
        // 302 to another origin, so Discord would answer the preflight with no
        // CORS headers and the visit would die as a network error. This answers
        // an Inertia request with a 409 telling the client to navigate properly,
        // and a normal request with an ordinary redirect.
        //
        // response_type=code with a redirect_uri is what makes Discord hand the
        // chosen guild back; scope=bot alone would just add the bot and stop.
        return Inertia::location('https://discord.com/oauth2/authorize?'.http_build_query([
            'client_id' => $clientId,
            'permissions' => (string) DiscordApi::BOT_PERMISSIONS,
            'scope' => 'bot',
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            // Preselect the server when the game already has one, so a
            // re-invite cannot silently land somewhere else.
            ...($game->hasDiscordGuild() ? [
                'guild_id' => $game->discord_guild_id,
                'disable_guild_select' => 'true',
            ] : []),
        ]));
    }

    /**
     * Discord sends Control back here with the server they chose.
     *
     * The authorisation code is deliberately not exchanged: a bot add needs no
     * user token, and `guild_id` on the redirect is the only thing wanted.
     */
    public function callback(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull(self::CONNECT_SESSION_KEY);

        $gameId = is_array($pending) && is_int($pending['game_id'] ?? null)
            ? $pending['game_id']
            : null;
        $state = is_array($pending) && is_string($pending['state'] ?? null)
            ? $pending['state']
            : null;

        $game = $gameId === null ? null : Game::query()->find($gameId);

        // A mismatched or missing state means this redirect did not start here.
        // The game comes out of the session rather than the URI, so this is
        // also where it is checked against who is Control of it: every other
        // route naming a game is gated on that by the router.
        if ($game === null || $state === null
            || ! hash_equals($state, (string) $request->query('state'))
            || ! ($request->user()?->isControlFor($game) ?? false)) {
            return to_route('control.games.index')->withErrors([
                'discord_guild_id' => 'That Discord authorisation did not match a request from this browser. Please try again.',
            ]);
        }

        $guildId = (string) $request->query('guild_id');

        if (! preg_match('/^\d{15,25}$/', $guildId)) {
            // Control cancelled, or Discord returned without a guild — which is
            // what an unregistered redirect URI looks like from this side.
            return to_route('control.games.show', $game)->withErrors([
                'discord_guild_id' => 'Discord did not tell us which server was chosen. If this keeps happening, check that '
                    .$this->redirectUri().' is registered as a redirect in the Discord Developer Portal.',
            ]);
        }

        $this->attachGuild($game, $guildId);

        // Discord lets the person untick permissions on the way through. Say so
        // now rather than failing halfway through provisioning.
        $missing = $this->missingPermissions($request->query('permissions'));

        if ($missing !== []) {
            return to_route('control.games.show', $game)->withErrors([
                'discord_guild_id' => 'The bot was added, but without '.implode(' and ', $missing)
                    .'. Add the bot again with every box ticked, then provision.',
            ]);
        }

        ProvisionDiscordGuild::markStatus($game, DiscordProvisionStatus::Queued);
        ProvisionDiscordGuildJob::dispatch($game->id);

        return to_route('control.games.show', $game)
            ->with('status', 'Bot added. Provisioning the Discord server now.');
    }

    /**
     * Point a game at a Discord server, or set its invite link.
     *
     * Kept alongside the OAuth flow for the case where Control already knows
     * the snowflake, or needs to correct one by hand.
     */
    public function update(Game $game, UpdateGameGuildRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (array_key_exists('discord_guild_id', $validated)) {
            $this->attachGuild($game, $validated['discord_guild_id']);
            unset($validated['discord_guild_id']);
        }

        if ($validated !== []) {
            $game->update($validated);
        }

        return back()->with('status', 'Discord server settings saved.');
    }

    /**
     * Create or reconcile the game's roles and channels.
     */
    public function provision(Game $game): RedirectResponse
    {
        if (! $game->hasDiscordGuild()) {
            return back()->withErrors([
                'discord_guild_id' => 'Set the game\'s Discord server ID before provisioning.',
            ]);
        }

        if (! $this->api->isConfigured()) {
            return back()->withErrors([
                'discord_guild_id' => 'DISCORD_BOT_TOKEN is not set, so the application cannot act as a bot yet.',
            ]);
        }

        if ($game->discord_provision_status->isInProgress()) {
            return back()->with('status', 'Provisioning is already running.');
        }

        ProvisionDiscordGuild::markStatus($game, DiscordProvisionStatus::Queued);

        // Note: on the `sync` queue driver this runs inline and Control waits
        // for it. That is tolerable locally; in production the queue worker
        // that auto-advance already needs will pick it up.
        ProvisionDiscordGuildJob::dispatch($game->id);

        return back()->with('status', 'Provisioning the Discord server. This takes a minute or two.');
    }

    /**
     * Delete every channel and role in the game's server, so the next provision
     * builds it from nothing.
     *
     * Deliberately not part of provisioning, and deliberately awkward to reach:
     * reconciling is safe mid-game precisely because it never deletes, and this
     * is the button that does. It exists for a test server that has collected
     * the leavings of a dozen runs. The confirmation lives in the request,
     * which will not let this through until Control has typed the game's name.
     */
    public function reset(Game $game, ResetDiscordGuildRequest $request): RedirectResponse
    {
        if (! $game->hasDiscordGuild()) {
            return back()->withErrors([
                'confirm' => 'This game has no Discord server to clear.',
            ]);
        }

        if (! $this->api->isConfigured()) {
            return back()->withErrors([
                'confirm' => 'DISCORD_BOT_TOKEN is not set, so the application cannot act as a bot yet.',
            ]);
        }

        if ($game->discord_provision_status->isInProgress()) {
            return back()->with('status', 'Something is already running against this server. Wait for it to finish.');
        }

        ProvisionDiscordGuild::markStatus($game, DiscordProvisionStatus::Resetting);

        ResetDiscordGuildJob::dispatch($game->id);

        return back()->with('status', 'Clearing the Discord server. Provision again once it reports back.');
    }

    /**
     * Re-push roles for everyone the game knows about.
     *
     * Provisioning creates the roles; this hands them out. Sign in does the
     * same thing for one player, so this is for the case Control cares about
     * more: the roster changed after everyone had already logged in.
     */
    public function syncRoles(Game $game): RedirectResponse
    {
        if (! $game->hasDiscordGuild()) {
            return back()->withErrors([
                'discord_guild_id' => 'Set the game\'s Discord server ID first.',
            ]);
        }

        $userIds = User::query()
            ->whereNotNull('discord_id')
            ->where(fn ($query) => $query
                ->where('is_control', true)
                ->orWhereHas('characters', fn ($characters) => $characters->where('game_id', $game->id))
                ->orWhereHas('controlMemberships', fn ($members) => $members->where('game_id', $game->id)))
            ->pluck('id');

        foreach ($userIds as $userId) {
            SyncDiscordRoles::dispatch($userId);
        }

        return back()->with('status', sprintf(
            'Queued a role sync for %d player%s.',
            $userIds->count(),
            $userIds->count() === 1 ? '' : 's',
        ));
    }

    /**
     * Write a server onto a game, forgetting anything recorded for a previous one.
     *
     * The recorded snowflakes only mean anything inside the guild they came
     * from. Keeping them across a move would have a reconcile try to patch
     * roles that live in somebody else's server.
     */
    private function attachGuild(Game $game, ?string $guildId): void
    {
        if ($guildId === $game->discord_guild_id) {
            return;
        }

        $game->discordResources()->delete();
        $game->discordMemberSyncs()->delete();

        $game->update([
            'discord_guild_id' => $guildId,
            // The old server's invite is no use for the new one.
            'discord_invite_url' => null,
            'discord_provision_status' => DiscordProvisionStatus::Idle,
            'discord_provision_message' => null,
            'discord_provisioned_at' => null,
        ]);
    }

    /**
     * Which of the bot's required permissions were not granted.
     *
     * @return array<int, string>
     */
    private function missingPermissions(mixed $granted): array
    {
        if (! is_numeric($granted)) {
            return [];
        }

        $names = [
            'Create Instant Invite' => 1 << 0,
            'Manage Channels' => 1 << 4,
            'Manage Roles' => 1 << 28,
            'Manage Webhooks' => 1 << 29,
        ];

        $granted = (int) $granted;

        return array_keys(array_filter(
            $names,
            fn (int $bit): bool => ($granted & $bit) !== $bit,
        ));
    }

    private function redirectUri(): string
    {
        return config('services.discord.bot_redirect')
            ?: route('control.games.discord.callback');
    }
}
