<?php

namespace App\Actions;

use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Support\EquipmentCardBlueprint;

/**
 * Give a game the Equipment card catalogue (rulebook 3.4.1).
 *
 * Idempotent and non-destructive, on the same terms as the Protection Card
 * catalogue: matched on the printed code, and a card Control has edited is left
 * as they left it.
 */
class SeedEquipmentCards
{
    /**
     * @return array<int, EquipmentCardType>
     */
    public function handle(Game $game): array
    {
        $existing = $game->equipmentCardTypes()->pluck('code')->flip();
        $created = [];

        foreach (EquipmentCardBlueprint::defaults() as $attributes) {
            if ($existing->has($attributes['code'])) {
                continue;
            }

            $created[] = $game->equipmentCardTypes()->create($attributes);
        }

        return $created;
    }
}
