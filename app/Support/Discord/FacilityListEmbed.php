<?php

namespace App\Support\Discord;

use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Support\FactionLogo;
use Illuminate\Support\Carbon;

/**
 * The Discord embeds for #facility-list: who owns what.
 *
 * Deliberately pure, like {@see GuildBlueprint}: it reads the roster and
 * returns a payload, touching neither Discord nor anything else. What players
 * are shown is therefore assertable in a unit test, which matters more here
 * than anywhere else in the application, because this is the one thing the
 * application shows to everyone at once. It reads the faction artwork directory
 * through {@see FactionLogo} for a Corporation's thumbnail, which is still a
 * read and still assertable - a test writes a logo and looks at the payload.
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
     * Discord's own limits on a message's embeds.
     *
     * @see https://discord.com/developers/docs/resources/message#embed-object-embed-limits
     */
    public const MAX_EMBEDS = 10;

    public const MAX_DESCRIPTION = 4096;

    public const MAX_TOTAL_CHARACTERS = 6000;

    /**
     * One embed per Corporation, coloured to match the Discord role its players
     * already wear and carrying its logo, so a Corporation is the same colour
     * and the same badge everywhere.
     *
     * An embed each rather than one embed of fields because a Corporation with
     * five Facilities is a block of text either way, and separating them gives
     * each a heading, a colour and its own space. The cost is Discord's cap of
     * ten embeds per message: a game with more Corporations than that gets the
     * first ten and a line saying so, which is a limit worth living with for a
     * game whose roster is five.
     *
     * @return array<string, mixed>
     */
    public static function payload(Game $game): array
    {
        $corporations = $game->corporations()
            ->with(['facilities' => fn ($query) => $query->orderBy('name'), 'facilities.facilityType'])
            ->orderBy('name')
            ->get();

        if ($corporations->isEmpty()) {
            return ['embeds' => [self::emptyEmbed($game)]];
        }

        $shown = $corporations->take(self::MAX_EMBEDS);
        $hidden = $corporations->count() - $shown->count();
        $turnNumber = $game->currentTurn()?->number;

        $embeds = $shown
            ->values()
            ->map(fn (Corporation $corporation): array => self::corporationEmbed($corporation, $turnNumber))
            ->all();

        // The heading goes on the first embed and the timestamp on the last, so
        // the message reads as one list rather than as several.
        $embeds[0]['author'] = ['name' => 'Facilities in Procatorion'];

        $last = count($embeds) - 1;
        $embeds[$last]['footer'] = ['text' => self::footerText($turnNumber, $hidden)];
        $embeds[$last]['timestamp'] = Carbon::now()->toIso8601String();

        return ['embeds' => $embeds];
    }

    /**
     * @return array<string, mixed>
     */
    private static function corporationEmbed(Corporation $corporation, ?int $turnNumber): array
    {
        $embed = [
            'title' => $corporation->name,
            'color' => GuildBlueprint::colourFor($corporation->name),
            'description' => self::facilityLines($corporation, $turnNumber),
        ];

        // A logo where there is one. Absolute, because Discord fetches the
        // image itself rather than resolving it against anything, and omitted
        // rather than empty where there is none: a Corporation Control invented
        // mid-game has no artwork, and neither does a checkout that has not had
        // the logos added to it.
        $logo = FactionLogo::urlFor($corporation->name);

        if ($logo !== null) {
            $embed['thumbnail'] = ['url' => $logo];
        }

        return $embed;
    }

    /**
     * One line per Facility, truncated on a line boundary rather than
     * mid-Facility: half a name reads as a different Facility.
     */
    private static function facilityLines(Corporation $corporation, ?int $turnNumber): string
    {
        $lines = $corporation->facilities
            // Grouped by type, so a Corporation's two Security Facilities sit
            // together rather than either end of an alphabetical list.
            ->sortBy(fn (Facility $facility): string => $facility->facilityType->name.' '.$facility->name)
            ->map(fn (Facility $facility): string => sprintf(
                '**%s** — %s%s',
                $facility->name,
                $facility->facilityType->name,
                $facility->isAvailableOnTurn($turnNumber) ? '' : ' *(building)*',
            ))
            ->values()
            ->all();

        if ($lines === []) {
            return '*No Facilities.*';
        }

        $description = '';

        foreach ($lines as $index => $line) {
            $candidate = $description === '' ? $line : $description."\n".$line;

            if (mb_strlen($candidate) > self::MAX_DESCRIPTION - 64) {
                return $description."\n".sprintf('*and %d more*', count($lines) - $index);
            }

            $description = $candidate;
        }

        return $description;
    }

    private static function footerText(?int $turnNumber, int $hidden): string
    {
        $text = $turnNumber === null
            ? 'Before the game began'
            : sprintf('As of turn %d', $turnNumber);

        if ($hidden > 0) {
            $text .= sprintf(' · %d more Corporation(s) not shown', $hidden);
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyEmbed(Game $game): array
    {
        return [
            'author' => ['name' => 'Facilities in Procatorion'],
            'title' => 'Nothing built yet',
            'description' => 'No Corporation has opened a Facility.',
            'color' => 0x2B6CB0,
            'footer' => ['text' => self::footerText($game->currentTurn()?->number, 0)],
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }
}
