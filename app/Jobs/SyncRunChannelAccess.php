<?php

namespace App\Jobs;

use App\Actions\GrantRunChannelAccess;
use App\Models\Run;
use App\Services\Discord\DiscordApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lets the Runners into the target Facility's channels, or takes them out.
 *
 * Queued and fail-soft, for the reason {@see SyncFacilityChannels} is: the sync
 * queue driver runs this inline, so a throw here would come back out of
 * RunEngine::begin() and stop a group going in. A run that happens in the wrong
 * channel is a nuisance; a run that cannot start because Discord is down would
 * lose the group its Action phase, and there is only one of those per turn.
 *
 * Revoking matters more than granting, which is why the failure is logged at
 * warning either way: a group left with a key to a Facility they are no longer
 * running against can read the Corporation talking about them.
 */
class SyncRunChannelAccess implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $runId,
        /** True to let them in, false to take them out again. */
        public bool $granting,
    ) {}

    public function handle(GrantRunChannelAccess $access, DiscordApi $api): void
    {
        if (! $api->isConfigured()) {
            // No bot: Facility channels are not a feature of this deployment,
            // which is not an error worth retrying.
            return;
        }

        $run = Run::query()
            ->with('game', 'facility.corporation', 'participants.character.user')
            ->find($this->runId);

        if ($run === null || blank($run->game->discord_guild_id)) {
            return;
        }

        try {
            $this->granting ? $access->grant($run) : $access->revoke($run);
        } catch (StrayRequestException $exception) {
            // Only reachable under Http::preventStrayRequests(), i.e. in tests.
            // Swallowing it there would let a test quietly believe it had
            // exercised Discord, so this one is deliberately loud.
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning($this->granting
                ? 'Could not let the Runners into the Facility\'s Discord channels.'
                : 'Could not remove the Runners from the Facility\'s Discord channels.', [
                    'run_id' => $run->id,
                    'message' => $exception->getMessage(),
                ]);
        }
    }
}
