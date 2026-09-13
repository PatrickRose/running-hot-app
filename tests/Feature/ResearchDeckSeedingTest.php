<?php

namespace Tests\Feature;

use App\Actions\SeedResearchDecks;
use App\Enums\ResearchSuit;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\ResearchCard;
use App\Support\CardMarking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * What a game's research decks open with (rulebook 3.2.1, 3.2.3).
 *
 * The rulebook says a Corporation's deck "begins as a fairly basic deck" and
 * describes the public one nowhere, so what is in either is Control's to set in
 * config/running_hot.php. What these hold onto is that the config can say all of
 * it: the run of numbers that makes up the bulk, and the particular cards it
 * cannot - a marking, a wild worth something of its own, uneven copies.
 *
 * The marking is the one that matters most. A card printed "No single" cannot
 * be alone in its set, App\Support\Equation enforces that, and a deck that
 * could not seed one meant the only way to get one into a game was to buy it
 * off the tech tree.
 */
class ResearchDeckSeedingTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
        $this->corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);
    }

    /**
     * @param  array<string, mixed>  $private
     * @param  array<string, mixed>  $public
     */
    private function writeDecks(array $private, array $public = ['values' => [], 'copies' => 0]): void
    {
        config([
            'running_hot.research.private_deck' => $private,
            'running_hot.research.public_deck' => $public,
            'running_hot.research.corporations' => [],
        ]);

        $this->game->researchCards()->delete();

        app(SeedResearchDecks::class)->handle($this->game);
    }

    /**
     * @return Collection<int, ResearchCard>
     */
    private function deck(): object
    {
        return $this->corporation->researchCards()->get();
    }

    public function test_the_shape_still_writes_one_card_of_each_value_in_every_suit(): void
    {
        $this->writeDecks(['values' => [1, 2, 3], 'copies' => 2, 'wild' => 1, 'wild_value' => 4]);

        // Three values, four suits, twice over, and one wild.
        $this->assertCount(25, $this->deck());

        foreach (ResearchSuit::all() as $suit) {
            $this->assertSame(
                6,
                $this->deck()->where('suit', $suit)->count(),
                $suit->value.' is short',
            );
        }

        $wild = $this->deck()->whereNull('suit')->sole();

        $this->assertSame(4, $wild->value);
        $this->assertSame([], $wild->markings());
    }

    public function test_a_card_can_be_printed_with_a_marking(): void
    {
        $this->writeDecks([
            'values' => [1],
            'copies' => 1,
            'cards' => [
                ['value' => 8, 'markings' => [['marking' => 'no_single']]],
            ],
        ]);

        $marked = $this->deck()->where('value', 8);

        // One in each suit, because an entry that names no suit means the same
        // as a value in the shape does.
        $this->assertCount(4, $marked);

        foreach ($marked as $card) {
            $this->assertEquals([CardMarking::noSingle()], $card->markings());
        }

        // And the ordinary cards are still ordinary.
        foreach ($this->deck()->where('value', 1) as $card) {
            $this->assertSame([], $card->markings());
        }
    }

    public function test_an_entry_can_name_one_suit_a_wild_and_a_count(): void
    {
        $this->writeDecks([
            'values' => [],
            'copies' => 0,
            'cards' => [
                ['value' => 7, 'suit' => ResearchSuit::Leaf->value],
                ['value' => 3, 'wild' => true, 'copies' => 2, 'markings' => [['marking' => 'no_single']]],
                ['value' => 5, 'suit' => ResearchSuit::Cog->value, 'copies' => 3],
            ],
        ]);

        $this->assertCount(6, $this->deck());

        $leaf = $this->deck()->where('value', 7)->sole();
        $this->assertSame(ResearchSuit::Leaf, $leaf->suit);

        $wilds = $this->deck()->whereNull('suit');
        $this->assertCount(2, $wilds);

        foreach ($wilds as $wild) {
            $this->assertSame(3, $wild->value);
            $this->assertEquals([CardMarking::noSingle()], $wild->markings());
        }

        $this->assertCount(3, $this->deck()->where('value', 5));
    }

    public function test_the_public_deck_is_described_the_same_way(): void
    {
        $this->writeDecks(
            ['values' => [], 'copies' => 0],
            [
                'values' => [],
                'copies' => 0,
                'cards' => [
                    ['value' => 9, 'wild' => true, 'markings' => [['marking' => 'no_single']]],
                ],
            ],
        );

        $card = $this->game->researchCards()->whereNull('corporation_id')->sole();

        $this->assertNull($card->suit);
        $this->assertSame(9, $card->value);
        $this->assertEquals([CardMarking::noSingle()], $card->markings());
    }

    public function test_a_card_can_be_printed_with_both_markings(): void
    {
        $this->writeDecks([
            'values' => [],
            'copies' => 0,
            'cards' => [
                [
                    'value' => 6,
                    'suit' => ResearchSuit::Leaf->value,
                    'markings' => [
                        ['marking' => 'no_single'],
                        ['marking' => 'restricted', 'suit' => ResearchSuit::Cog->value],
                    ],
                ],
            ],
        ]);

        $card = $this->deck()->sole();

        $this->assertEquals([
            CardMarking::noSingle(),
            CardMarking::restrictedTo(ResearchSuit::Cog),
        ], $card->markings());
    }

    public function test_a_restricted_card_that_names_no_suit_stops_the_seed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('without naming the suit the other side must be');

        // Half a marking is worse on a card than none: "Restricted" with no
        // suit would check nothing at all and nothing would say why.
        $this->writeDecks([
            'values' => [],
            'copies' => 0,
            'cards' => [['value' => 6, 'markings' => [['marking' => 'restricted']]]],
        ]);
    }

    public function test_a_marking_the_rules_do_not_have_stops_the_seed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Gordon's research deck marks a card \"No Single\"");

        // A capital letter is the whole of the mistake, and writing the card
        // with no marking at all would be worse than refusing it: it would go
        // on being playable alone for the rest of the game.
        $this->writeDecks([
            'values' => [],
            'copies' => 0,
            'cards' => [['value' => 8, 'markings' => [['marking' => 'No Single']]]],
        ]);
    }

    public function test_a_suit_the_game_does_not_have_stops_the_seed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('names a suit "spades" that does not exist');

        $this->writeDecks([
            'values' => [],
            'copies' => 0,
            'cards' => [['value' => 4, 'suit' => 'spades']],
        ]);
    }

    public function test_a_card_cannot_be_wild_and_a_suit_at_once(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('both wild and leaf');

        $this->writeDecks([
            'values' => [],
            'copies' => 0,
            'cards' => [['value' => 4, 'wild' => true, 'suit' => ResearchSuit::Leaf->value]],
        ]);
    }

    public function test_a_card_with_no_value_stops_the_seed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('names a card with no value');

        $this->writeDecks([
            'values' => [],
            'copies' => 0,
            'cards' => [['markings' => [['marking' => 'no_single']]]],
        ]);
    }

    public function test_a_deck_that_has_already_been_written_is_left_alone(): void
    {
        $this->writeDecks([
            'values' => [],
            'copies' => 0,
            'cards' => [['value' => 2, 'suit' => ResearchSuit::Brain->value]],
        ]);

        $this->assertCount(1, $this->deck());

        $result = app(SeedResearchDecks::class)->handle($this->game);

        $this->assertSame(0, $result['private']);
        $this->assertCount(1, $this->deck());
    }
}
