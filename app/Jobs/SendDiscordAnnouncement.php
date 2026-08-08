<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    ) {}

    public function handle(): void
    {
        $payload = array_filter([
            'content' => $this->content,
            'embeds' => $this->embeds !== [] ? $this->embeds : null,
        ], fn ($value) => $value !== null);

        try {
            Http::asJson()
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->post($this->webhookUrl, $payload)
                ->throw();
        } catch (Throwable $exception) {
            Log::warning('Discord announcement failed.', [
                'message' => $exception->getMessage(),
                'content' => $this->content,
            ]);
        }
    }
}
