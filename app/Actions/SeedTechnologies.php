<?php

namespace App\Actions;

use App\Models\Corporation;
use App\Models\Game;
use App\Support\TechnologyBlueprint;
use Illuminate\Database\Eloquent\Collection;

/**
 * Give a game the technologies on its Corporations' tech trees (rulebook 3.2.2).
 *
 * Runs twice on the way to a full game, and has to be safe both times. A game is
 * created before its roster exists, so the first run writes every technology
 * with no Corporation attached; the second, after App\Actions\CreateDefaultRoster
 * has built the Corporations, attaches each tree to the Corporation it belongs
 * to. Both runs are the same call.
 *
 * Attaching is by the Corporation's name, and only ever fills a blank. A
 * technology Control has already moved to another Corporation keeps that, and a
 * tree whose Corporation this game does not have stays unattached rather than
 * being forced onto a Corporation it does not belong to - the tree column still
 * records where it came from.
 *
 * Idempotent and non-destructive otherwise, on the same terms as the other
 * catalogues: matched on the printed code, and a technology Control has repriced
 * is left as they left it.
 */
class SeedTechnologies
{
    /**
     * @return array{created: int, linked: int}
     */
    public function handle(Game $game): array
    {
        $facilityTypes = $game->facilityTypes()->pluck('id', 'key');
        $created = 0;

        foreach (TechnologyBlueprint::defaults() as $attributes) {
            $requiredType = $attributes['requires_facility_type'];
            unset($attributes['requires_facility_type']);

            $technology = $game->technologyTypes()->firstOrCreate(
                ['code' => $attributes['code']],
                [
                    ...$attributes,
                    // A type Control has renamed away leaves the requirement
                    // unset rather than inventing one: a technology that can be
                    // housed anywhere is a smaller problem than one pinned to a
                    // Facility type the game does not have.
                    'required_facility_type_id' => $requiredType === null
                        ? null
                        : $facilityTypes->get($requiredType),
                ],
            );

            if ($technology->wasRecentlyCreated) {
                $created++;
            }
        }

        return ['created' => $created, 'linked' => $this->linkToCorporations($game)];
    }

    /**
     * Attach each tree to its Corporation, where this game has one by that name.
     */
    private function linkToCorporations(Game $game): int
    {
        /** @var Collection<int, Corporation> $corporations */
        $corporations = $game->corporations()->get();

        if ($corporations->isEmpty()) {
            return 0;
        }

        $linked = 0;

        foreach (TechnologyBlueprint::corporationNames() as $tree => $name) {
            $corporation = $corporations->firstWhere('name', $name);

            if ($corporation === null) {
                continue;
            }

            $linked += $game->technologyTypes()
                ->where('tree', $tree)
                ->whereNull('corporation_id')
                ->update(['corporation_id' => $corporation->id]);
        }

        return $linked;
    }
}
