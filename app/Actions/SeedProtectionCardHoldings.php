<?php

namespace App\Actions;

use App\Models\Corporation;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

/**
 * Give each Corporation the Protection Cards its briefing says it owns.
 *
 * Keyed by the code printed on the card rather than by title, because titles
 * repeat and a code does not. A code the catalogue does not have is skipped: the
 * catalogue is Control's, and a briefing naming a card they have deleted is not
 * a reason to put it back.
 *
 * A Corporation the configuration says nothing about opens with no cards rather
 * than a guessed set. That is the same choice App\Actions\CreateDefaultFacilities
 * makes about Facilities, and for the same reason - a Corporation Control
 * invented has a briefing only they have seen.
 *
 * Runs once, as part of writing a game's starting position. Re-running only ever
 * adds a card a Corporation has no row for at all, so it cannot quietly refill
 * a hand that has been spent during play.
 */
class SeedProtectionCardHoldings
{
    /**
     * @return array{corporations: int, holdings: int, copies: int}
     */
    public function handle(Game $game): array
    {
        $codes = $game->protectionCardTypes()
            ->whereNotNull('code')
            ->pluck('id', 'code');

        return DB::transaction(function () use ($game, $codes): array {
            $corporations = 0;
            $holdings = 0;
            $copies = 0;

            foreach ($game->corporations()->orderBy('name')->get() as $corporation) {
                $configured = $this->configuredCopies($corporation);

                if ($configured === []) {
                    continue;
                }

                $corporations++;

                foreach ($configured as $code => $count) {
                    $cardTypeId = $codes->get((string) $code);

                    if ($cardTypeId === null) {
                        continue;
                    }

                    $holding = $corporation->protectionCardHoldings()->firstOrCreate(
                        ['protection_card_type_id' => $cardTypeId],
                        ['copies' => (int) $count],
                    );

                    if ($holding->wasRecentlyCreated) {
                        $holdings++;
                        $copies += (int) $count;
                    }
                }
            }

            return ['corporations' => $corporations, 'holdings' => $holdings, 'copies' => $copies];
        });
    }

    /**
     * The copies this Corporation's briefing gives it, keyed by card code.
     *
     * @return array<string, int>
     */
    private function configuredCopies(Corporation $corporation): array
    {
        /** @var array<int, array<string, mixed>> $configured */
        $configured = config('running_hot.corporations', []);

        foreach ($configured as $entry) {
            if (($entry['name'] ?? null) !== $corporation->name) {
                continue;
            }

            /** @var array<string, int> $cards */
            $cards = $entry['protection_cards'] ?? [];

            return $cards;
        }

        return [];
    }
}
