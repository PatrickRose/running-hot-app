<?php

namespace App\Actions;

use App\Enums\DiscordResourceKind;
use App\Models\DiscordResource;
use App\Models\Facility;
use App\Services\Discord\DiscordApi;
use App\Support\Discord\ChannelPayload;
use App\Support\Discord\GuildBlueprint;
use App\Support\Discord\PlannedChannel;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Gives one Facility its text and voice channels.
 *
 * Facilities appear mid-game — a requisition raised during Setup opens the next
 * turn — and provisioning is a batch run Control triggers. Re-provisioning the
 * whole guild for one new Facility would re-PATCH every channel and role in it,
 * which Discord rate-limits hard, so this creates just the pair.
 *
 * {@see ProvisionDiscordGuild} still reconciles them on its next run, so a game
 * whose bot token arrived late catches up, and anything deleted by hand comes
 * back. Neither path ever deletes: a Facility Control removes keeps its channel,
 * because the Run that happened in it is still worth reading.
 */
class ProvisionFacilityChannels
{
    public function __construct(private readonly DiscordApi $api) {}

    /**
     * @return array<int, string> the keys of the channels created
     */
    public function handle(Facility $facility): array
    {
        $game = $facility->game;

        if (blank($game->discord_guild_id) || ! $this->api->isConfigured()) {
            return [];
        }

        $guildId = $game->discord_guild_id;
        $recorded = $game->discordResources()->get()->keyBy('key');

        $categoryKey = GuildBlueprint::facilityCategoryKey($facility->corporation);
        $reason = sprintf('Running Hot: channels for %s', $facility->name);

        // Snowflakes the payload builder resolves overwrites and parents from.
        $roleIds = $recorded
            ->where('kind', DiscordResourceKind::Role)
            ->map(fn (DiscordResource $resource): string => $resource->discord_id)
            ->all();

        $channelIds = $recorded
            ->whereIn('kind', [DiscordResourceKind::Category, DiscordResourceKind::TextChannel, DiscordResourceKind::VoiceChannel])
            ->map(fn (DiscordResource $resource): string => $resource->discord_id)
            ->all();

        // The category the pair hangs off. A Corporation whose Facilities have
        // never been provisioned has none yet, so it is made first.
        if (! isset($channelIds[$categoryKey])) {
            $category = new PlannedChannel(
                key: $categoryKey,
                kind: DiscordResourceKind::Category,
                name: $facility->corporation->name.' Facilities',
                overwrites: GuildBlueprint::channelsForFacility($facility)[0]->overwrites,
            );

            $created = $this->api->createChannel(
                $guildId,
                ChannelPayload::for($category, $guildId, $roleIds, $channelIds),
                $reason,
            );

            $channelIds[$categoryKey] = (string) ($created['id'] ?? '');
            $this->record($facility, $category, $channelIds[$categoryKey]);
        }

        $keys = [];

        foreach (GuildBlueprint::channelsForFacility($facility) as $planned) {
            if ($recorded->has($planned->key)) {
                continue;
            }

            $created = $this->api->createChannel(
                $guildId,
                ChannelPayload::for($planned, $guildId, $roleIds, $channelIds),
                $reason,
            );

            $this->record($facility, $planned, (string) ($created['id'] ?? ''));
            $keys[] = $planned->key;
        }

        if ($keys !== []) {
            Log::info('Discord channels created for a Facility.', [
                'game_id' => $game->id,
                'facility_id' => $facility->id,
                'keys' => $keys,
            ]);
        }

        return $keys;
    }

    private function record(Facility $facility, PlannedChannel $planned, string $discordId): void
    {
        if ($discordId === '') {
            throw new RuntimeException(sprintf(
                'Discord returned no id for the channel [%s].',
                $planned->key,
            ));
        }

        DiscordResource::query()->updateOrCreate(
            ['game_id' => $facility->game_id, 'key' => $planned->key],
            ['kind' => $planned->kind, 'discord_id' => $discordId, 'name' => $planned->name],
        );
    }
}
