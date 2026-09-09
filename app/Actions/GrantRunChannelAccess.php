<?php

namespace App\Actions;

use App\Enums\DiscordResourceKind;
use App\Models\DiscordResource;
use App\Models\Run;
use App\Models\RunParticipant;
use App\Services\Discord\DiscordApi;
use App\Support\Discord\GuildBlueprint;
use Illuminate\Support\Facades\Log;

/**
 * Puts the Runners into the Facility they are hitting, and takes them out again.
 *
 * Every Facility already has a private text and voice channel in its
 * Corporation's category, locked to Control and that Corporation - which is
 * where a Run against it happens. This is the half that lets the attackers in.
 *
 * **Not a moment earlier than the run starting.** Targets are chosen in Secret
 * (rulebook 3.4.1), and a Runner appearing in #burngreave-vault before they go
 * in would tell the whole server who was hitting what. Access is granted as the
 * group goes in and removed when the run ends, which is exactly as long as
 * they are standing in the place.
 *
 * **A Runner who walks away at the Breather keeps their access until the run
 * is over.** They already know the target, so nothing new leaks, and they want
 * to know how it went - the rulebook is neutral about leaving and taking
 * somebody out of the conversation would be a punishment it never printed.
 *
 * Per-member overwrites rather than a role, because this is something that
 * happens to those four people for the next ten minutes rather than a standing
 * fact about the guild - and a "currently running" role would have to be
 * created, granted, revoked and cleaned up on a crash.
 */
class GrantRunChannelAccess
{
    /**
     * What a Runner may do in the Facility they are breaking into.
     *
     * Speak as well as read: a Run is a conversation under time pressure, and
     * the voice channel is the point of having one.
     */
    public const RUNNER_PERMISSIONS = DiscordApi::VIEW_CHANNEL
        | DiscordApi::SEND_MESSAGES
        | DiscordApi::CONNECT
        | DiscordApi::SPEAK;

    public function __construct(private readonly DiscordApi $api) {}

    /**
     * Let the group into the Facility's channels.
     *
     * @return array<int, string> the Discord ids granted access
     */
    public function grant(Run $run): array
    {
        return $this->apply($run, granting: true);
    }

    /**
     * Take the group back out.
     *
     * Only ever removes the overwrites this action would have added, so a
     * player Control let into a Facility's channel by hand keeps their access -
     * the same reasoning role syncing uses for a role granted by hand.
     *
     * @return array<int, string> the Discord ids removed
     */
    public function revoke(Run $run): array
    {
        return $this->apply($run, granting: false);
    }

    /**
     * @return array<int, string>
     */
    private function apply(Run $run, bool $granting): array
    {
        $game = $run->game;

        if (blank($game->discord_guild_id) || ! $this->api->isConfigured()) {
            return [];
        }

        $channelIds = $this->channelIds($run);

        if ($channelIds === []) {
            // The Facility has no channels on record. That happens when a
            // Discord outage caught the requisition, and the Control panel
            // already badges it as fixable - so there is nothing to do here but
            // say so quietly rather than fail a run over it.
            Log::info('A run started against a Facility with no Discord channels.', [
                'run_id' => $run->id,
                'facility_id' => $run->facility_id,
            ]);

            return [];
        }

        $discordIds = $this->discordIds($run);
        $reason = sprintf(
            $granting
                ? 'Running Hot: run %d in progress at %s'
                : 'Running Hot: run %d at %s is over',
            $run->id,
            $run->facility->name,
        );

        foreach ($channelIds as $channelId) {
            foreach ($discordIds as $discordId) {
                if ($granting) {
                    $this->api->setChannelPermission(
                        $channelId,
                        $discordId,
                        [
                            'type' => DiscordApi::OVERWRITE_MEMBER,
                            // Strings, because a permission set is a 64-bit
                            // bitfield and Discord sends and takes it as one.
                            'allow' => (string) self::RUNNER_PERMISSIONS,
                            'deny' => '0',
                        ],
                        $reason,
                    );
                } else {
                    $this->api->deleteChannelPermission($channelId, $discordId, $reason);
                }
            }
        }

        if ($discordIds !== []) {
            Log::info($granting
                ? 'Runners let into a Facility\'s Discord channels.'
                : 'Runners removed from a Facility\'s Discord channels.', [
                    'run_id' => $run->id,
                    'facility_id' => $run->facility_id,
                    'runners' => count($discordIds),
                ]);
        }

        return $discordIds;
    }

    /**
     * The Facility's two channels, as snowflakes.
     *
     * @return array<int, string>
     */
    private function channelIds(Run $run): array
    {
        $keys = [
            GuildBlueprint::facilityChannelKey($run->facility, 'text'),
            GuildBlueprint::facilityChannelKey($run->facility, 'voice'),
        ];

        return $run->game->discordResources()
            ->whereIn('key', $keys)
            ->whereIn('kind', [DiscordResourceKind::TextChannel, DiscordResourceKind::VoiceChannel])
            ->get()
            ->map(fn (DiscordResource $resource): string => $resource->discord_id)
            ->all();
    }

    /**
     * Everybody who set out, as Discord snowflakes.
     *
     * Everybody rather than everybody still in, because access lasts the run:
     * see the note on the class. A Runner whose character nobody has claimed,
     * or who has never signed in with Discord, simply has no snowflake to grant
     * anything to - which is normal rather than a failure, since a character is
     * set up before anybody claims it.
     *
     * @return array<int, string>
     */
    private function discordIds(Run $run): array
    {
        return $run->participants()
            ->with('character.user')
            ->get()
            ->map(fn (RunParticipant $participant): ?string => $participant->character->user?->discord_id)
            ->filter(fn (?string $id): bool => filled($id))
            ->unique()
            ->values()
            ->all();
    }
}
