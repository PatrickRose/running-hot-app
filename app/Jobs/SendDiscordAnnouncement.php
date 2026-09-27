<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts a message to a Discord incoming webhook.
 *
 * Announcements are deliberately fail-soft: a Discord outage must never stop
 * the turn clock, so a failed post is logged and swallowed.
 */
class SendDiscordAnnouncement implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<int, array<string, mixed>>  $embeds
     */
    public function __construct(
        public string $webhookUrl,
        public string $content,
        public array $embeds = [],
        public ?string $avatarUrl = null,
    ) {}

    public function handle(): void
    {
        $payload = array_filter([
            'content' => $this->content,
            'embeds' => $this->embeds !== [] ? $this->embeds : null,
            // Discord fetches the avatar itself, so a dev server it cannot
            // reach simply posts with the webhook's own picture instead.
            'avatar_url' => $this->avatarUrl,
            // Only @everyone is allowed to ping: a character name that happens
            // to look like a role or user mention must not notify anybody.
            'allowed_mentions' => ['parse' => ['everyone']],
        ], fn ($value) => $value !== null);

        try {
            Http::asJson()
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->post($this->webhookUrl, $payload)
                ->throw();
        } catch (StrayRequestException $exception) {
            // Only reachable under Http::preventStrayRequests(), i.e. in tests.
            // Swallowing it there would let a test quietly believe it had
            // exercised Discord, so this one is deliberately loud.
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Discord announcement failed.', [
                'message' => $exception->getMessage(),
                'content' => $this->content,
            ]);
        }
    }
}
