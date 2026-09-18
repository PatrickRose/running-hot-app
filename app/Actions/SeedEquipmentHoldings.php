<?php

namespace App\Actions;

use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\EquipmentHolding;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

/**
 * Give each Runner the Equipment their briefing says they start with.
 *
 * The companion to App\Actions\SeedProtectionCardHoldings, and it makes the same
 * three choices for the same reasons:
 *
 * - **Keyed by the code printed on the card** rather than by title, because
 *   titles repeat across the seventy-four and a code does not.
 * - **A code the catalogue does not have is skipped.** The catalogue is
 *   Control's, and a briefing naming a card they have deleted is not a reason to
 *   put it back.
 * - **A gang the configuration says nothing about opens with nothing**, rather
 *   than a guessed kit. A gang Control invented has a briefing only they have
 *   seen.
 *
 * Copies land on the **Character**, because that is where a holding lives: 3.4.1
 * caps *you* at three equipped permanent items and 3.4.2 hands *your* permanent
 * Equipment to Security when you are carried out. Neither sentence means
 * anything about a shared pile.
 *
 * The briefings are written per faction, so the common case is a gang-level list
 * that every Runner in it starts with - five Runners each carrying their own
 * copy of the same card, not five sharing one. A per-Runner list adds to that,
 * for the kit one named Runner has and their gangmates do not.
 *
 * Runs once, as part of writing a game's starting position. Re-running only ever
 * adds a card a Runner has no row for at all, so it cannot quietly refill a hand
 * that has been spent during play.
 */
class SeedEquipmentHoldings
{
    /**
     * @return array{runners: int, holdings: int, copies: int}
     */
    public function handle(Game $game): array
    {
        $codes = $game->equipmentCardTypes()
            ->whereNotNull('code')
            ->pluck('id', 'code');

        return DB::transaction(function () use ($game, $codes): array {
            $runners = 0;
            $holdings = 0;
            $copies = 0;

            $characters = $game->characters()
                ->whereIn('role', [CharacterRole::Runner, CharacterRole::Freelancer])
                ->with('gang')
                ->get();

            foreach ($characters as $character) {
                $configured = $this->configuredCopies($character);

                if ($configured === []) {
                    continue;
                }

                $runners++;

                foreach ($configured as $code => $count) {
                    $typeId = $codes->get($code);

                    if ($typeId === null || $count < 1) {
                        continue;
                    }

                    $holding = EquipmentHolding::query()->firstOrCreate(
                        [
                            'character_id' => $character->id,
                            'equipment_card_type_id' => $typeId,
                        ],
                        ['copies' => $count],
                    );

                    if ($holding->wasRecentlyCreated) {
                        $holdings++;
                        $copies += $count;
                    }
                }
            }

            return ['runners' => $runners, 'holdings' => $holdings, 'copies' => $copies];
        });
    }

    /**
     * What one Runner starts with: their gang's kit, plus anything the
     * configuration gives them by name on top of it.
     *
     * @return array<string, int>
     */
    private function configuredCopies(Character $character): array
    {
        $gangName = $character->gang?->name;

        if ($gangName === null) {
            // A Freelancer belongs to no gang and has a briefing of their own,
            // so they are named directly rather than through a faction.
            /** @var array<string, array<string, int>> $freelancers */
            $freelancers = config('running_hot.freelancer_equipment', []);

            return $freelancers[$character->name] ?? [];
        }

        /** @var array<int, array<string, mixed>> $gangs */
        $gangs = config('running_hot.gangs', []);

        foreach ($gangs as $gang) {
            if (($gang['name'] ?? null) !== $gangName) {
                continue;
            }

            /** @var array<string, int> $shared */
            $shared = $gang['equipment'] ?? [];

            /** @var array<int, array<string, mixed>> $runners */
            $runners = $gang['runners'] ?? [];

            foreach ($runners as $runner) {
                if (($runner['name'] ?? null) !== $character->name) {
                    continue;
                }

                /** @var array<string, int> $own */
                $own = $runner['equipment'] ?? [];

                // Their own kit adds to the gang's rather than replacing it, so
                // a briefing that says "everyone carries a Medkit, and Ghost
                // also has a Katana" reads that way in the configuration.
                foreach ($own as $code => $count) {
                    $shared[$code] = ($shared[$code] ?? 0) + (int) $count;
                }

                break;
            }

            return $shared;
        }

        return [];
    }
}
