<?php

namespace App\Support\Discord;

use App\Enums\DiscordResourceKind;
use App\Services\Discord\DiscordApi;

/**
 * Turns a {@see PlannedChannel} into the body Discord's channel endpoints want.
 *
 * Shared rather than written twice, because the interesting part is the
 * permission overwrites: a channel that silently loses its lock is invisible
 * until a player reads something they should not have. Two copies of this
 * would be two chances for them to drift apart.
 */
class ChannelPayload
{
    /**
     * @param  array<string, string>  $roleIds  role snowflakes, keyed by blueprint key
     * @param  array<string, string>  $channelIds  channel snowflakes, keyed by blueprint key
     * @return array<string, mixed>
     */
    public static function for(
        PlannedChannel $planned,
        string $guildId,
        array $roleIds,
        array $channelIds,
    ): array {
        $payload = [
            'name' => $planned->name,
            'type' => $planned->kind->channelType(),
        ];

        if ($planned->parentKey !== null && isset($channelIds[$planned->parentKey])) {
            $payload['parent_id'] = $channelIds[$planned->parentKey];
        }

        // Discord rejects a topic on a voice channel, so only text gets one.
        if ($planned->topic !== null && $planned->kind === DiscordResourceKind::TextChannel) {
            $payload['topic'] = $planned->topic;
        }

        $overwrites = [];

        foreach ($planned->overwrites as $overwrite) {
            // The guild's default role shares the guild's snowflake, which is
            // how Discord expresses "@everyone".
            $target = $overwrite->target === PlannedOverwrite::EVERYONE
                ? $guildId
                : ($roleIds[$overwrite->target] ?? null);

            if ($target === null) {
                continue;
            }

            $overwrites[] = [
                'id' => $target,
                'type' => DiscordApi::OVERWRITE_ROLE,
                'allow' => (string) $overwrite->allow,
                'deny' => (string) $overwrite->deny,
            ];
        }

        $payload['permission_overwrites'] = $overwrites;

        return $payload;
    }
}
