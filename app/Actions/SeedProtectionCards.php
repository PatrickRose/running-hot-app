<?php

namespace App\Actions;

use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Support\ProtectionCardBlueprint;

/**
 * Give a game the Protection Card catalogue (rulebook 3.3.2, 3.3.3).
 *
 * Matched on the code printed on the card rather than on the title, because
 * titles repeat: Doppleganger is PX011 in the physical stack and PX012 in the
 * cyber one.
 *
 * Idempotent, and it never overwrites. If Control has repriced a card or moved
 * one from rumoured to on sale, re-running leaves that alone and only adds back
 * a card that is missing entirely - the catalogue is theirs once the game
 * exists.
 */
class SeedProtectionCards
{
    /**
     * @return array<int, ProtectionCardType>
     */
    public function handle(Game $game): array
    {
        $created = [];

        foreach (ProtectionCardBlueprint::defaults() as $attributes) {
            $card = $game->protectionCardTypes()->firstOrCreate(
                ['code' => $attributes['code']],
                $attributes,
            );

            if ($card->wasRecentlyCreated) {
                $created[] = $card;
            }
        }

        return $created;
    }
}
