<?php

namespace App\Actions;

use App\Enums\ResearchSuit;
use App\Enums\ResearchZone;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\ResearchCard;

/**
 * Give a game the decks the research game is played out of (rulebook 3.2.1).
 *
 * The public deck belongs to the game and each Corporation gets a private one.
 * Both are described in config/running_hot.php as a shape rather than as a list
 * of cards, because the rulebook says only that a research deck "begins as a
 * fairly basic deck" and never describes the public one at all - so what is in
 * them is Control's to set.
 *
 * A starting position rather than a change: the cards are simply written down,
 * nothing goes through TrackerService, and there is no before state to record.
 *
 * Runs after App\Actions\CreateDefaultRoster, because a private deck needs a
 * Corporation to belong to - and runs safely at any later point too. A deck
 * that already has cards in it is left alone, so a Corporation added mid-game
 * gets a deck and one that has been playing all evening does not get a second
 * one shuffled into the first.
 */
class SeedResearchDecks
{
    /**
     * @return array{public: int, private: int}
     */
    public function handle(Game $game): array
    {
        return [
            'public' => $this->seedPublicDeck($game),
            'private' => $this->seedPrivateDecks($game),
        ];
    }

    /**
     * The shared deck the six-card pool is dealt from.
     */
    private function seedPublicDeck(Game $game): int
    {
        $existing = $game->researchCards()->publicCards()->exists();

        if ($existing) {
            return 0;
        }

        /** @var array<string, mixed> $shape */
        $shape = config('running_hot.research.public_deck', []);

        return $this->write($game, null, $shape);
    }

    /**
     * One deck per Corporation, and no deck for anybody else: the research game
     * is a Corporate sub-game.
     */
    private function seedPrivateDecks(Game $game): int
    {
        /** @var array<string, array<string, mixed>> $named */
        $named = config('running_hot.research.corporations', []);

        /** @var array<string, mixed> $default */
        $default = config('running_hot.research.private_deck', []);

        $written = 0;

        foreach ($game->corporations()->get() as $corporation) {
            if ($corporation->researchCards()->exists()) {
                continue;
            }

            $written += $this->write(
                $game,
                $corporation,
                $named[$corporation->name] ?? $default,
            );
        }

        return $written;
    }

    /**
     * Turn one deck shape into rows, shuffled.
     *
     * The shuffle is here rather than left to the first deal, so a deck that
     * has been written down but not yet dealt is already in a random order -
     * which matters for the public deck, since Control may look at it before
     * the first Action phase.
     *
     * @param  array<string, mixed>  $shape
     */
    private function write(Game $game, ?Corporation $corporation, array $shape): int
    {
        /** @var array<int, int> $values */
        $values = $shape['values'] ?? [];
        $copies = max(0, (int) ($shape['copies'] ?? 1));
        $wild = max(0, (int) ($shape['wild'] ?? 0));
        $wildValue = (int) ($shape['wild_value'] ?? 1);

        $cards = [];

        for ($copy = 0; $copy < $copies; $copy++) {
            foreach (ResearchSuit::all() as $suit) {
                foreach ($values as $value) {
                    $cards[] = ['suit' => $suit->value, 'value' => (int) $value];
                }
            }
        }

        for ($index = 0; $index < $wild; $index++) {
            $cards[] = ['suit' => null, 'value' => $wildValue];
        }

        shuffle($cards);

        $now = now();
        $rows = [];

        foreach ($cards as $position => $card) {
            $rows[] = [
                'game_id' => $game->id,
                'corporation_id' => $corporation?->id,
                'suit' => $card['suit'],
                'value' => $card['value'],
                'zone' => ResearchZone::Deck->value,
                'position' => $position + 1,
                'restriction' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            ResearchCard::query()->insert($rows);
        }

        return count($rows);
    }
}
