<?php

namespace App\Actions;

use App\Enums\ResearchCardMarking;
use App\Enums\ResearchSuit;
use App\Enums\ResearchZone;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\ResearchCard;
use App\Support\CardMarking;
use InvalidArgumentException;

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
     * Turn one deck's description into rows, shuffled.
     *
     * Two ways of saying what is in a deck, and they add together. The bulk of
     * one is a shape - `values` is one card of each value in every suit,
     * `copies` repeats that, `wild` adds that many cards of no suit - because
     * most of a deck is the same run of numbers four times over and writing it
     * out would bury the parts that are not. `cards` is those parts: entries
     * written one at a time, which is the only way to say that a card carries a
     * marking, that a wild is worth something other than the rest of them, or
     * that there are two 3s and one 5.
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
        $deck = $corporation === null
            ? 'The public deck'
            : sprintf("%s's research deck", $corporation->name);

        $cards = array_merge(
            $this->fromShape($shape),
            $this->fromList($shape, $deck),
        );

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
                // insert() goes round the model, so the cast does not run:
                // the list has to reach the driver already encoded.
                'markings' => json_encode($card['markings']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            ResearchCard::query()->insert($rows);
        }

        return count($rows);
    }

    /**
     * The run of numbers that makes up most of a deck.
     *
     * @param  array<string, mixed>  $shape
     * @return array<int, array{suit: string|null, value: int, markings: array<int, array{marking: string, suit: string|null}>}>
     */
    private function fromShape(array $shape): array
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
                    $cards[] = [
                        'suit' => $suit->value,
                        'value' => (int) $value,
                        'markings' => [],
                    ];
                }
            }
        }

        for ($index = 0; $index < $wild; $index++) {
            $cards[] = ['suit' => null, 'value' => $wildValue, 'markings' => []];
        }

        return $cards;
    }

    /**
     * The cards a deck names one at a time.
     *
     * An entry is a value, and then what makes it particular: `suit` for one
     * card of that suit, `wild` for a card of none, and neither for one in each
     * of the four - which is what the shape above means by a value as well.
     * `markings` is a list of what the card is printed with - each a `marking`
     * and, where the marking names one, a `suit` - and `copies` how many of
     * whatever the entry describes. A list rather than one, because the two
     * markings are about different halves of the equation and a card may carry
     * both.
     *
     * A suit or a marking the application does not have stops the seed rather
     * than being written as a null, and so does a Restricted that names no suit.
     * A deck is Control's to describe and this is the one place a typo in it
     * would be silent: a card that quietly lost its "No single" would go on
     * being playable alone for the rest of the game.
     *
     * @param  array<string, mixed>  $shape
     * @return array<int, array{suit: string|null, value: int, markings: array<int, array{marking: string, suit: string|null}>}>
     */
    private function fromList(array $shape, string $deck): array
    {
        /** @var array<int, array<string, mixed>> $entries */
        $entries = $shape['cards'] ?? [];

        $cards = [];

        foreach ($entries as $entry) {
            $value = (int) ($entry['value'] ?? 0);

            if ($value < 1) {
                throw new InvalidArgumentException(sprintf(
                    '%s names a card with no value. Every card is worth at least 1.',
                    $deck,
                ));
            }

            $wild = (bool) ($entry['wild'] ?? false);
            $named = $entry['suit'] ?? null;

            if ($wild && $named !== null) {
                throw new InvalidArgumentException(sprintf(
                    '%s names a card that is both wild and %s. A wild card has no suit.',
                    $deck,
                    (string) $named,
                ));
            }

            /** @var array<int, ResearchSuit|null> $suits */
            $suits = match (true) {
                $wild => [null],
                $named !== null => [$this->suit((string) $named, $deck)],
                default => ResearchSuit::all(),
            };

            $markings = $this->markings($entry['markings'] ?? [], $deck);
            $copies = max(1, (int) ($entry['copies'] ?? 1));

            for ($copy = 0; $copy < $copies; $copy++) {
                foreach ($suits as $suit) {
                    $cards[] = [
                        'suit' => $suit?->value,
                        'value' => $value,
                        'markings' => CardMarking::listToArray($markings),
                    ];
                }
            }
        }

        return $cards;
    }

    private function suit(string $key, string $deck): ResearchSuit
    {
        $suit = ResearchSuit::tryFrom($key);

        if ($suit === null) {
            throw new InvalidArgumentException(sprintf(
                '%s names a suit "%s" that does not exist. The suits are: %s.',
                $deck,
                $key,
                implode(', ', array_map(
                    fn (ResearchSuit $suit): string => $suit->value,
                    ResearchSuit::all(),
                )),
            ));
        }

        return $suit;
    }

    /**
     * The markings one entry names, checked before they can reach a card.
     *
     * @param  mixed  $entries
     * @return array<int, CardMarking>
     */
    private function markings($entries, string $deck): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $markings = [];

        foreach ($entries as $entry) {
            $key = is_array($entry) ? ($entry['marking'] ?? null) : $entry;
            $marking = ResearchCardMarking::tryFrom((string) $key);

            if ($marking === null) {
                throw new InvalidArgumentException(sprintf(
                    '%s marks a card "%s", which is not a marking the rules know. The markings are: %s.',
                    $deck,
                    (string) $key,
                    implode(', ', array_map(
                        fn (ResearchCardMarking $known): string => $known->value,
                        ResearchCardMarking::all(),
                    )),
                ));
            }

            $suit = is_array($entry) ? ($entry['suit'] ?? null) : null;

            if ($marking->namesASuit() && ($suit === null || $suit === '')) {
                throw new InvalidArgumentException(sprintf(
                    '%s marks a card "%s" without naming the suit the other side must be.',
                    $deck,
                    $marking->label(),
                ));
            }

            $markings[] = new CardMarking(
                $marking,
                $marking->namesASuit() ? $this->suit((string) $suit, $deck) : null,
            );
        }

        return $markings;
    }
}
