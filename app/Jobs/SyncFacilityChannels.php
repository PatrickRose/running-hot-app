<?php

namespace App\Jobs;

use App\Actions\ProvisionFacilityChannels;
use App\Models\Facility;
use App\Services\Discord\DiscordApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gives a newly built Facility its Discord channels.
 *
 * Queued, so raising a requisition never waits on Discord and never fails
 * because Discord is down. A Facility with no channels is a nuisance; a
 * requisition that cannot be signed off because Discord is unreachable would
 * stop the game.
 */
class SyncFacilityChannels implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $facilityId) {}

    public function handle(ProvisionFacilityChannels $provision, DiscordApi $api): void
    {
        if (! $api->isConfigured()) {
            // No bot configured: Facility channels are simply not a feature of
            // this deployment, which is not an error worth retrying.
            return;
        }

        $facility = Facility::query()->with('corporation', 'game')->find($this->facilityId);

        if ($facility === null || blank($facility->game->discord_guild_id)) {
            return;
        }

        try {
            $provision->handle($facility);
        } catch (StrayRequestException $exception) {
            // Only reachable under Http::preventStrayRequests(), i.e. in tests.
            // Swallowing it there would let a test quietly believe it had
            // exercised Discord, so this one is deliberately loud.
            throw $exception;
        } catch (Throwable $exception) {
            // Fail-soft, and for a sharper reason than most: the sync queue
            // driver runs this inline, so an exception here would come back out
            // of Facility::create() and take the requisition down with it. A
            // Facility with no channel is a nuisance; a CEO who cannot sign off
            // a build because Discord is down would stop the game.
            Log::warning('Could not create Discord channels for a Facility.', [
                'facility_id' => $facility->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
