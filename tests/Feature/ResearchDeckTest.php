<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\ResearchSuit;
use App\Enums\ResearchZone;
use App\Enums\Tracker;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ResearchCard;
use App\Models\TechnologyType;
use App\Services\ResearchTableService;
use App\Services\TrackerService;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Deck customisation (rulebook 3.2.3).
 *
 * "The costs for this are denoted in your tech tree, and should be handled as if
 * you are researching any other technology" - and those six rows do not price
 * like anything else on the tree, because the suits are the player's choice:
 * "spend 4 research credits in any suit", "6 in any suit and 3 in another",
 * "5 from each suit". So a price is a list of amounts and the player says which
 * suit each comes out of, all different.
 */
class ResearchDeckTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'running_hot.research.private_deck' => ['values' => [1, 2], 'copies' => 1, 'wild' => 0],
            'running_hot.research.public_deck' => ['values' => [1, 2], 'copies' => 1, 'wild' => 0],
        ]);

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);

        $trackers = app(TrackerService::class);

        foreach (ResearchSuit::all() as $suit) {
            $trackers->set($this->corporation, $suit->tracker(), 20);
        }

        $this->corporation->refresh();
    }

    private function table(): ResearchTableService
    {
        return app(ResearchTableService::class);
    }

    /**
     * @param  array<string, mixed>  $grant
     */
    private function entry(array $grant = [], string $name = 'Research deck'): TechnologyType
    {
        return TechnologyType::factory()->for($this->game)->create([
            'name' => $name,
            'cog_cost' => 0,
            'brain_cost' => 0,
            'leaf_cost' => 0,
            'maths_cost' => 0,
            'deck_grant' => [
                'amounts' => [4],
                'value_min' => 3,
                'value_max' => 5,
                'wild' => false,
                'restriction' => null,
                'requires_research_facilities' => 0,
                ...$grant,
            ],
        ]);
    }

    private function researchFacilities(int $count): void
    {
        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        Facility::factory()
            ->count($count)
            ->for($this->corporation)
            ->for($type)
            ->create();
    }

    public function test_the_games_own_deck_rows_are_seeded_with_what_they_grant(): void
    {
        /** @var TechnologyType $first */
        $first = $this->game->technologyTypes()->where('code', 'RSR035')->sole();

        $this->assertSame([
            'amounts' => [4],
            'value_min' => 3,
            'value_max' => 5,
            'wild' => false,
            'restriction' => 'No single',
            'requires_research_facilities' => 0,
        ], $first->deckGrant());

        // "5 research credits from each suit ... a wild card ... between 3-5"
        /** @var TechnologyType $wild */
        $wild = $this->game->technologyTypes()->where('code', 'RSR039')->sole();

        $this->assertSame([5, 5, 5, 5], $wild->deckGrant()['amounts']);
        $this->assertTrue($wild->deckGrant()['wild']);
        $this->assertSame(8, $wild->deckGrant()['requires_research_facilities']);
    }

    public function test_buying_a_card_charges_the_chosen_suit_and_shuffles_it_in(): void
    {
        $card = $this->table()->customiseDeck(
            $this->corporation,
            $this->entry(['restriction' => 'No single']),
            [ResearchSuit::Leaf],
            4,
        );

        $this->assertSame(ResearchSuit::Leaf, $card->suit);
        $this->assertSame(4, $card->value);
        $this->assertSame('No single', $card->restriction);
        // Into the deck, not the hand: buying a card is not a free draw.
        $this->assertSame(ResearchZone::Deck, $card->zone);
        $this->assertSame($this->corporation->id, $card->corporation_id);

        $this->assertSame(16, $this->corporation->refresh()->leaf_points);

        $this->assertDatabaseHas('tracker_adjustments', [
            'subject_id' => $this->corporation->id,
            'tracker' => Tracker::ResearchLeaf->value,
            'delta' => -4,
            'reason' => 'Deck customisation: Research deck',
        ]);
    }

    public function test_the_card_takes_the_suit_of_the_first_amount(): void
    {
        $card = $this->table()->customiseDeck(
            $this->corporation,
            $this->entry(['amounts' => [6, 3]]),
            [ResearchSuit::Maths, ResearchSuit::Cog],
            3,
        );

        $this->assertSame(ResearchSuit::Maths, $card->suit);
        $this->assertSame(14, $this->corporation->refresh()->maths_points);
        $this->assertSame(17, $this->corporation->refresh()->cog_points);
    }

    public function test_a_wild_row_adds_a_card_of_no_suit(): void
    {
        $card = $this->table()->customiseDeck(
            $this->corporation,
            $this->entry([
                'amounts' => [5, 5, 5, 5],
                'wild' => true,
            ]),
            ResearchSuit::all(),
            5,
        );

        $this->assertTrue($card->isWild());
        $this->assertSame('Wild 5', $card->label());

        foreach (ResearchSuit::all() as $suit) {
            $this->assertSame(15, (int) $this->corporation->refresh()->{$suit->pointsColumn()});
        }
    }

    public function test_each_part_of_the_price_has_to_be_a_different_suit(): void
    {
        $this->expectException(ValidationException::class);

        $this->table()->customiseDeck(
            $this->corporation,
            $this->entry(['amounts' => [6, 3]]),
            [ResearchSuit::Leaf, ResearchSuit::Leaf],
            3,
        );
    }

    public function test_the_value_has_to_be_inside_the_range_the_row_prints(): void
    {
        $this->expectException(ValidationException::class);

        $this->table()->customiseDeck(
            $this->corporation,
            $this->entry(['value_min' => 3, 'value_max' => 5]),
            [ResearchSuit::Leaf],
            9,
        );
    }

    public function test_a_row_that_asks_for_research_facilities_counts_them(): void
    {
        $entry = $this->entry(['requires_research_facilities' => 3]);

        $this->researchFacilities(2);

        try {
            $this->table()->customiseDeck($this->corporation, $entry, [ResearchSuit::Leaf], 3);
            $this->fail('Two Research Facilities should not have been enough.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('needs 3 Research Facilities', $exception->getMessage());
        }

        $this->researchFacilities(1);

        $card = $this->table()->customiseDeck($this->corporation, $entry, [ResearchSuit::Leaf], 3);

        $this->assertSame(3, $card->value);
    }

    public function test_a_corporation_that_cannot_pay_is_refused(): void
    {
        app(TrackerService::class)->set($this->corporation, ResearchSuit::Leaf->tracker(), 1);

        $this->expectException(ValidationException::class);

        $this->table()->customiseDeck(
            $this->corporation,
            $this->entry(),
            [ResearchSuit::Leaf],
            3,
        );
    }

    public function test_control_upgrades_a_card_already_in_a_deck(): void
    {
        /** @var ResearchCard $card */
        $card = $this->corporation->researchCards()->create([
            'game_id' => $this->game->id,
            'suit' => ResearchSuit::Cog,
            'value' => 2,
            'zone' => ResearchZone::Deck,
        ]);

        $this->table()->editCard($card, ResearchSuit::Brain, 8, 'No single');

        $this->assertSame(ResearchSuit::Brain, $card->refresh()->suit);
        $this->assertSame(8, $card->value);
        $this->assertSame('No single', $card->restriction);
    }

    public function test_a_technology_that_grants_nothing_is_not_deck_customisation(): void
    {
        $technology = TechnologyType::factory()->for($this->game)->create(['name' => 'Laser Porridge']);

        $this->expectException(ValidationException::class);

        $this->table()->customiseDeck($this->corporation, $technology, [ResearchSuit::Leaf], 3);
    }
}
