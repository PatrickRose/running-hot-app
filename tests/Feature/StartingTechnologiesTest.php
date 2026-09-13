<?php

namespace Tests\Feature;

use App\Actions\CreateDefaultFacilities;
use App\Actions\CreateDefaultRoster;
use App\Actions\GrantStartingTechnologies;
use App\Enums\TechnologyHoldingStatus;
use App\Enums\TechnologyOrigin;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Services\TechnologyService;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The technologies a Corporation opens the game already holding
 * (rulebook 3.2.2).
 *
 * Twenty across the five Corporations, and they are a starting position rather
 * than a purchase: free, housed, and with no Research Points moving because
 * none were ever spent. What these hold onto is that they are *held* - a
 * starting technology that only sat on the tree would leave ANT opening without
 * Power, which is its whole position.
 */
class StartingTechnologiesTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
    }

    private function openGame(): void
    {
        app(CreateDefaultRoster::class)->handle($this->game);
        app(CreateDefaultFacilities::class)->handle($this->game);
    }

    private function technologies(): TechnologyService
    {
        return app(TechnologyService::class);
    }

    /**
     * @return array<int, string>
     */
    private function heldBy(string $corporation): array
    {
        /** @var Corporation $corp */
        $corp = $this->game->corporations()->where('name', $corporation)->sole();

        return $corp->technologyHoldings()
            ->with('technologyType')
            ->get()
            ->map(fn (TechnologyHolding $holding): string => $holding->technologyType->name)
            ->sort()
            ->values()
            ->all();
    }

    public function test_every_corporation_opens_holding_its_own_starting_technologies(): void
    {
        $this->openGame();

        $this->assertSame([
            'Power (Part 1/4)', 'Power (Part 2/4)', 'Power (Part 3/4)', 'Power (Part 4/4)',
        ], $this->heldBy('Augmented Nucleotech'));

        $this->assertSame([
            'Arms (Part 1/4)', 'Arms (Part 2/4)', 'Arms (Part 3/4)', 'Arms (Part 4/4)',
        ], $this->heldBy('Digital Tactical Control'));

        $this->assertSame([
            'Miracle Genetics (Part 1/4)', 'Miracle Genetics (Part 2/4)',
            'Miracle Genetics (Part 3/4)', 'Miracle Genetics (Part 4/4)',
        ], $this->heldBy('Genetic Equity'));

        // Gordon's three are plot hooks rather than split pieces, and carry a
        // real description instead of the words "Starting tech" - which is why
        // the marker is a column and not that string.
        $this->assertSame([
            'Genetic Equity story', 'Skarlo', 'Slow roll',
        ], $this->heldBy('Gordon'));

        $this->assertSame([
            'Construction leader (Part 1/2)', 'Construction leader (Part 2/2)',
            'Factory (Part 1/3)', 'Factory (Part 2/3)', 'Factory (Part 3/3)',
        ], $this->heldBy('McCullough Calibrated Mechanical'));

        $this->assertSame(20, $this->game->technologyHoldings()->count());
    }

    public function test_a_starting_technology_is_researched_free_and_housed(): void
    {
        $this->openGame();

        foreach ($this->game->technologyHoldings()->get() as $holding) {
            $this->assertSame(TechnologyHoldingStatus::Researched, $holding->status);
            // Researched rather than shared, which is what lets 3.2.7 give its
            // owner a split technology on one piece.
            $this->assertSame(TechnologyOrigin::Researched, $holding->origin);
            $this->assertSame(0, array_sum($holding->paid()));
            $this->assertNotNull($holding->facility_id);
        }

        // Nothing was bought, so nothing moved through the ledger.
        $this->assertSame(0, $this->game->trackerAdjustments()->count());
    }

    public function test_a_split_starting_technology_works_on_any_one_piece(): void
    {
        $this->openGame();

        /** @var Corporation $ant */
        $ant = $this->game->corporations()->where('name', 'Augmented Nucleotech')->sole();

        $this->assertSame(4, $this->technologies()->piecesHeld($ant, 'Power'));

        foreach ($ant->technologyHoldings()->get() as $piece) {
            $this->assertTrue($this->technologies()->isUsable($piece));
        }

        // And still works once a Run has taken three of the four, because the
        // Corporation researched it rather than stealing it.
        $ant->technologyHoldings()->get()->skip(1)->each(
            fn (TechnologyHolding $holding) => $this->technologies()->destroy($holding),
        );

        $survivor = $ant->technologyHoldings()->researched()->sole();

        $this->assertTrue($this->technologies()->isUsable($survivor));
    }

    public function test_a_technology_that_names_a_facility_type_is_housed_there(): void
    {
        $this->openGame();

        /** @var FacilityType $factory */
        $factory = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::FACTORY)->sole();

        $housed = TechnologyHolding::query()
            ->whereHas('technologyType', fn ($query) => $query->where('code', 'RMR015'))
            ->with('facility')
            ->sole();

        $this->assertSame($factory->id, $housed->facility->facility_type_id);
    }

    public function test_the_starting_cards_are_spread_rather_than_stacked(): void
    {
        $this->openGame();

        /** @var Corporation $ant */
        $ant = $this->game->corporations()->where('name', 'Augmented Nucleotech')->sole();

        // Four pieces across four Facilities: a Runner cannot take all of Power
        // in one Run, and the storage a Corporation researches into stays open.
        $this->assertSame(
            4,
            $ant->technologyHoldings()->distinct()->count('facility_id'),
        );
    }

    public function test_a_free_technology_that_is_not_a_starting_one_is_not_granted(): void
    {
        $this->openGame();

        // Fourteen technologies cost nothing without being anybody's starting
        // position - the six deck customisation rows among them - so cost must
        // not be what decides this.
        $names = $this->game->technologyHoldings()
            ->with('technologyType')
            ->get()
            ->map(fn (TechnologyHolding $holding): string => $holding->technologyType->name);

        $this->assertFalse($names->contains('Research deck'));
        $this->assertFalse($names->contains('Overseers'));
    }

    public function test_granting_twice_hands_out_nothing_more(): void
    {
        $this->openGame();

        $result = app(GrantStartingTechnologies::class)->handle($this->game);

        $this->assertSame(0, $result['granted']);
        $this->assertSame(20, $this->game->technologyHoldings()->count());
    }

    public function test_a_corporation_with_nowhere_to_house_one_is_reported_rather_than_thrown(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create(['name' => 'Nowhere Industries']);

        /** @var FacilityType $research */
        $research = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        // A Research Facility and no Corporate one, so storage is zero: "2
        // multiplied by the number of Corporate Facilities you have".
        Facility::factory()->for($corporation)->for($research)->create(['name' => 'Nowhere Labs']);

        TechnologyType::factory()->for($this->game)->create([
            'name' => 'Nowhere special',
            'corporation_id' => $corporation->id,
            'starting' => true,
        ]);

        $result = app(GrantStartingTechnologies::class)->handle($this->game);

        $this->assertSame(0, $result['granted']);
        $this->assertSame(['Nowhere Industries: Nowhere special'], $result['unhoused']);
        $this->assertSame(0, $corporation->technologyHoldings()->count());
    }
}
