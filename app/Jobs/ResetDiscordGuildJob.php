<?php

namespace App\Jobs;

use App\Actions\ProvisionDiscordGuild;
use App\Actions\ResetDiscordGuild;
use App\Enums\DiscordProvisionStatus;
use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clears a game's guild on the queue, for the same reason provisioning is
 * queued: it is one rate-limited Discord call per object in the server.
 *
 * One attempt, and emphatically so. Provisioning can be retried because it is
 * idempotent; this deletes things, and a retry that ran against a half-cleared
 * guild would be a second wipe nobody asked for. Control re-runs it themselves
 * if the first pass left anything behind, and the message says how much it did.
 */
class ResetDiscordGuildJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $gameId) {}

    public function handle(ResetDiscordGuild $reset): void
    {
        $game = Game::query()->find($this->gameId);

        if ($game === null) {
            return;
        }

        try {
            $reset->handle($game);
        } catch (Throwable $exception) {
            Log::error('Clearing the Discord server failed.', [
                'game_id' => $game->id,
                'message' => $exception->getMessage(),
            ]);

            // Failed rather than Idle: a half-cleared guild is a state Control
            // has to look at, and Idle would read as "ready to provision".
            ProvisionDiscordGuild::markStatus(
                $game,
                DiscordProvisionStatus::Failed,
                'Clearing the server failed: '.$exception->getMessage(),
            );
        }
    }
}
