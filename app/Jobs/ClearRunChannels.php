<?php

namespace App\Jobs;

use App\Actions\ClearRunChannels as ClearRunChannelsAction;
use App\Models\Turn;
use App\Services\Discord\DiscordApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clears the run channels once a turn is over.
 *
 * Queued and fail-soft, for the reason {@see SyncRunChannelAccess} is: the sync
 * queue driver runs this inline, so a throw here would come back out of
 * TurnEngine::advance() and stop the clock. A channel that still has last
 * turn's conversation in it is a leak worth fixing; a turn that cannot end
 * because Discord is down would stop the game, and the clock is the one thing
 * this application must never drop.
 *
 * So a failure leaves the messages where they are and says so. Control can
 * clear the channel by hand, and the next turn's sweep will not pick these up
 * again - it only ever looks at the turn it was given - which is worth knowing
 * rather than worth retrying into.
 */
class ClearRunChannels implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $turnId) {}

    public function handle(ClearRunChannelsAction $clear, DiscordApi $api): void
    {
        if (! $api->isConfigured()) {
            // No bot: Facility channels are not a feature of this deployment.
            return;
        }

        $turn = Turn::query()->with('game')->find($this->turnId);

        if ($turn === null || blank($turn->game->discord_guild_id)) {
            return;
        }

        try {
            $clear->handle($turn);
        } catch (StrayRequestException $exception) {
            // Only reachable under Http::preventStrayRequests(), i.e. in tests.
            // Swallowing it there would let a test quietly believe it had
            // exercised Discord, so this one is deliberately loud.
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Could not clear the run channels after the turn.', [
                'turn_id' => $turn->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
