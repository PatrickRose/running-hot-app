<?php

namespace App\Http\Controllers\Control;

use App\Actions\ProvisionDiscordGuild;
use App\Enums\DiscordProvisionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\UpdateGameGuildRequest;
use App\Jobs\ProvisionDiscordGuildJob;
use App\Jobs\SyncDiscordRoles;
use App\Models\Game;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use Illuminate\Http\RedirectResponse;

/**
 * Control's half of the bot integration: which server a game lives in, and the
 * two buttons that push the application's idea of it into Discord.
 */
class DiscordGuildController extends Controller
{
    public function __construct(private readonly DiscordApi $api) {}

    /**
     * Point a game at a Discord server, or set its invite link.
     */
    public function update(Game $game, UpdateGameGuildRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $previous = $game->discord_guild_id;

        $game->update($validated);

        // Moving to a different server invalidates every recorded snowflake,
        // so forget them: reconciling against the new guild must not try to
        // patch roles that live somewhere else.
        if (array_key_exists('discord_guild_id', $validated) && $validated['discord_guild_id'] !== $previous) {
            $game->discordResources()->delete();
            $game->discordMemberSyncs()->delete();

            $game->update([
                'discord_provision_status' => DiscordProvisionStatus::Idle,
                'discord_provision_message' => null,
                'discord_provisioned_at' => null,
            ]);
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
                ->orWhereHas('characters', fn ($characters) => $characters->where('game_id', $game->id)))
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
}
