<?php

namespace App\Jobs;

use App\Actions\ProvisionDiscordGuild;
use App\Enums\DiscordProvisionStatus;
use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provisions a game's guild on the queue.
 *
 * This is queued because it is dozens of rate-limited Discord calls — role
 * creation in particular is throttled hard — which is far too slow to hold an
 * HTTP request open for. Control watches progress through the game's
 * `discord_provision_status`, which the panel already polls.
 */
class ProvisionDiscordGuildJob implements ShouldQueue
{
    use Queueable;

    /**
     * One attempt. A half-finished run leaves recorded resources behind, so a
     * retry would be a fresh reconcile anyway — and Control should decide when
     * to try again rather than have the queue hammer a rate limit.
     */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $gameId) {}

    public function handle(ProvisionDiscordGuild $provision): void
    {
        $game = Game::query()->find($this->gameId);

        if ($game === null) {
            return;
        }

        ProvisionDiscordGuild::markStatus($game, DiscordProvisionStatus::Running);

        try {
            $tally = $provision->handle($game);
        } catch (Throwable $exception) {
            Log::error('Discord provisioning failed.', [
                'game_id' => $game->id,
                'message' => $exception->getMessage(),
            ]);

            ProvisionDiscordGuild::markStatus(
                $game,
                DiscordProvisionStatus::Failed,
                $exception->getMessage(),
            );

            return;
        }

        ProvisionDiscordGuild::markStatus($game, DiscordProvisionStatus::Completed, sprintf(
            '%d role(s) created, %d updated; %d channel(s) created, %d updated.',
            $tally['roles_created'],
            $tally['roles_updated'],
            $tally['channels_created'],
            $tally['channels_updated'],
        ));
    }
}
