<?php

namespace App\Support\Discord;

use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use Illuminate\Support\Carbon;

/**
 * The Discord embed for #facility-list: who owns what.
 *
 * Deliberately pure, like {@see GuildBlueprint}: it reads the roster and
 * returns a payload, touching neither Discord nor anything else. What players
 * are shown is therefore assertable in a unit test, which matters more here
 * than anywhere else in the application, because this is the one thing the
 * application shows to everyone at once.
 *
 * WHAT MUST NOT GO IN HERE. The channel is visible to Runners, and rulebook
 * 3.4.2 says outright that "The number of Protection Cards that a Facility
 * contains is Secret". Technology contents are secret for the same reason -
 * reconnaissance exists so that finding out costs something. So this carries a
 * Corporation, a Facility name and a type, and nothing else. Adding a stack
 * size here would hand every Runner in the game a free recon action.
 *
 * Facilities still building are listed and marked, because a construction site
 * is a visible thing.
 */
class FacilityListEmbed
{
    /**
     * Discord's own limits on an embed.
     *
     * @see https://discord.com/developers/docs/resources/message#embed-object-embed-limits
     */
    public const MAX_FIELDS = 25;

    public const MAX_FIELD_VALUE = 1024;

    /**
     * One inline field per Corporation, which Discord renders as a grid of
     * three across. Fields rather than an embed each because Discord allows 25
     * of them against 10 embeds, so this holds a far bigger game.
     *
     * @return array<string, mixed>
     */
    public static function payload(Game $game): array
    {
        $turn = $game->currentTurn();

        return [
            'embeds' => [[
                'title' => 'Facilities',
                'description' => 'Every Facility in Procatorion, and who owns it. '
                    ."What is installed in them is not public knowledge.\n\n"
                    .'Use a reconnaissance action to learn more.',
                'color' => 0x2B6CB0,
                'fields' => self::fields($game),
                'footer' => [
                    'text' => $turn === null
                        ? 'Before the game began'
                        : sprintf('As of turn %d', $turn->number),
                ],
                'timestamp' => Carbon::now()->toIso8601String(),
            ]],
        ];
    }

    /**
     * @return array<int, array{name: string, value: string, inline: bool}>
     */
    private static function fields(Game $game): array
    {
        $corporations = $game->corporations()
            ->with(['facilities' => fn ($query) => $query->orderBy('name'), 'facilities.facilityType'])
            ->orderBy('name')
            ->limit(self::MAX_FIELDS)
            ->get();

        if ($corporations->isEmpty()) {
            return [[
                'name' => 'Nothing built yet',
                'value' => 'No Corporation has opened a Facility.',
                'inline' => false,
            ]];
        }

        $turnNumber = $game->currentTurn()?->number;

        return $corporations
            ->map(fn (Corporation $corporation): array => [
                'name' => $corporation->name,
                'value' => self::facilityLines($corporation, $turnNumber),
                'inline' => true,
            ])
            ->values()
            ->all();
    }

    /**
     * One line per Facility, truncated on a line boundary rather than
     * mid-Facility: half a name reads as a different Facility.
     */
    private static function facilityLines(Corporation $corporation, ?int $turnNumber): string
    {
        $lines = $corporation->facilities
            ->map(fn (Facility $facility): string => sprintf(
                '%s — %s%s',
                $facility->name,
                $facility->facilityType->name,
                $facility->isAvailableOnTurn($turnNumber) ? '' : ' *(building)*',
            ))
            ->all();

        if ($lines === []) {
            return '*No Facilities.*';
        }

        $value = '';

        foreach ($lines as $index => $line) {
            $candidate = $value === '' ? $line : $value."\n".$line;

            if (mb_strlen($candidate) > self::MAX_FIELD_VALUE - 32) {
                return $value."\n".sprintf('*and %d more*', count($lines) - $index);
            }

            $value = $candidate;
        }

        return $value;
    }
}
