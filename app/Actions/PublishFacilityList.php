<?php

namespace App\Actions;

use App\Enums\DiscordResourceKind;
use App\Models\DiscordResource;
use App\Models\Game;
use App\Services\Discord\DiscordApi;
use App\Support\Discord\FacilityListEmbed;
use App\Support\Discord\GuildBlueprint;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Puts the Facility list in the game's #facility-list channel, as an embed.
 *
 * The announcement webhook cannot do this, because a webhook only ever posts to
 * the channel it was made in. So this is the bot's job, and a game with no bot
 * token or no provisioned server simply has no list.
 *
 * Posted once and then rewritten. A list that changes every time a Facility
 * opens would otherwise leave the channel full of superseded copies, and a
 * player reading the wrong one is worse than a player reading none - this is a
 * game where reconnaissance exists so that knowing things costs something.
 *
 * Control publishes it; the application never creates it unprompted. Once it
 * exists {@see refresh()} keeps it current as the game moves, so nobody has to
 * remember to press the button again.
 */
class PublishFacilityList
{
    public const MESSAGE_KEY = 'message:facility-list';

    public function __construct(private readonly DiscordApi $api) {}

    /**
     * Post the list, or rewrite it where it has already been posted.
     *
     * @return array{action: string, message_id: string}
     */
    public function handle(Game $game): array
    {
        $channel = $this->channel($game);

        if ($channel === null) {
            throw ValidationException::withMessages([
                'facility_list' => 'This game has no #facility-list channel yet. Provision its Discord server first.',
            ]);
        }

        if (! $this->api->isConfigured()) {
            throw ValidationException::withMessages([
                'facility_list' => 'DISCORD_BOT_TOKEN is not set, so the application cannot post as the bot.',
            ]);
        }

        $payload = FacilityListEmbed::payload($game);
        $existing = $this->existingMessage($game);

        if ($existing !== null) {
            $this->api->editMessage($channel->discord_id, $existing->discord_id, $payload);

            return ['action' => 'edited', 'message_id' => $existing->discord_id];
        }

        $message = $this->api->createMessage($channel->discord_id, $payload);
        $messageId = (string) ($message['id'] ?? '');

        if ($messageId !== '') {
            $this->record($game, $messageId);
        }

        return ['action' => 'posted', 'message_id' => $messageId];
    }

    /**
     * Bring an already-published list up to date, and do nothing otherwise.
     *
     * Called when the game moves rather than by Control, so it is fail-soft for
     * the same reason announcements are: a Discord outage must never stall the
     * clock. It also never posts a list that does not already exist, so the
     * application cannot surprise a live server with one.
     */
    public function refresh(Game $game): bool
    {
        if ($this->existingMessage($game) === null || ! $this->api->isConfigured()) {
            return false;
        }

        try {
            $this->handle($game);
        } catch (Throwable) {
            // Reported nowhere on purpose: the list being a turn out of date is
            // not worth interrupting the game for, and Control can republish.
            return false;
        }

        return true;
    }

    public function hasBeenPublished(Game $game): bool
    {
        return $this->existingMessage($game) !== null;
    }

    private function existingMessage(Game $game): ?DiscordResource
    {
        return DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('key', self::MESSAGE_KEY)
            ->first();
    }

    private function channel(Game $game): ?DiscordResource
    {
        return DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('key', GuildBlueprint::CHANNEL_FACILITY_LIST)
            ->first();
    }

    private function record(Game $game, string $messageId): void
    {
        DiscordResource::query()->updateOrCreate(
            ['game_id' => $game->id, 'key' => self::MESSAGE_KEY],
            [
                'kind' => DiscordResourceKind::Message,
                'discord_id' => $messageId,
                'name' => 'Facility list',
            ],
        );
    }
}
