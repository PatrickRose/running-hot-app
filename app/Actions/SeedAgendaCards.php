<?php

namespace App\Actions;

use App\Models\AgendaCard;
use App\Models\Game;
use App\Services\CouncilService;
use App\Support\AgendaCardBlueprint;

/**
 * Give a game the agenda deck the Council votes out of (rulebook 3.1.1).
 *
 * Matched on the title of a card nobody submitted, because agenda cards carry
 * no printed code and a player may write a custom agenda of their own with the
 * same name (3.1.3). Their card is not a copy of Control's and must never stand
 * in for one.
 *
 * Idempotent, and it never overwrites. A card the Chair has amended, or one
 * Control has rewritten, is left exactly as it is on a re-run - the deck is
 * theirs once the game exists. Only a card missing entirely comes back.
 *
 * Every card goes in through CouncilService, so the deck cannot be seeded past
 * the two-to-five bound 3.1.4 puts on a card's resolutions. Nothing in the
 * blueprint breaks it, and AgendaCardBlueprintTest is what keeps that true;
 * this is the belt to that pair of braces.
 */
class SeedAgendaCards
{
    public function __construct(private readonly CouncilService $council) {}

    /**
     * @return array<int, AgendaCard>
     */
    public function handle(Game $game): array
    {
        $created = [];

        foreach (AgendaCardBlueprint::defaults() as $blueprint) {
            $exists = $game->agendaCards()
                ->whereNull('submitted_by_character_id')
                ->where('title', $blueprint['title'])
                ->exists();

            if ($exists) {
                continue;
            }

            $created[] = $this->council->createDeckCard(
                $game,
                $blueprint['title'],
                null,
                $blueprint['resolutions'],
            );
        }

        return $created;
    }
}
