<?php

namespace App\Actions;

use App\Models\FacilityType;
use App\Models\Game;
use App\Support\FacilityTypeBlueprint;

/**
 * Give a game the starting Facility type catalogue (rulebook 3.3.1).
 *
 * Idempotent, and it never overwrites: if Control has renamed Research or
 * changed what a Security Facility grants, re-running leaves those edits alone
 * and only adds back a type that is missing entirely.
 */
class SeedFacilityTypes
{
    /**
     * @return array<int, FacilityType>
     */
    public function handle(Game $game): array
    {
        $existing = $game->facilityTypes()->pluck('key')->flip();
        $created = [];

        foreach (FacilityTypeBlueprint::defaults() as $attributes) {
            if ($existing->has($attributes['key'])) {
                continue;
            }

            $created[] = $game->facilityTypes()->create($attributes);
        }

        return $created;
    }
}
