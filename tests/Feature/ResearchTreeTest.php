<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\ResearchSuit;
use App\Enums\TechnologyHoldingStatus;
use App\Enums\TechnologyOrigin;
use App\Enums\Tracker;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Services\TechnologyService;
use App\Services\TrackerService;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Spending Research Points on the tree (rulebook 3.2.2) and everything that
 * happens to a technology afterwards (3.2.5 to 3.2.7).
 *
 * The three rules worth pinning down are the ones easiest to get wrong: a
 * technology has to be housed and cannot be researched if there is nowhere to
 * put it (footnote 7); storage per Facility is two for every *Corporate*
 * Facility the Corporation owns, so a Corporation with none can store nothing;
 * and a split technology works for the Corporation that researched it on one
 * piece, while a thief needs them all (3.2.7).
 */
class ResearchTreeTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    private Facility $research;

    private Facility $corporate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);

        $this->research = $this->facility(FacilityTypeBlueprint::RESEARCH, 'Owlerton Laboratories');
        // One Corporate Facility, so every Facility stores two technologies.
        $this->corporate = $this->facility(FacilityTypeBlueprint::CORPORATE, 'Gordon Tower');

        $this->givePoints(['cog' => 20, 'brain' => 20, 'leaf' => 20, 'maths' => 20]);
    }

    private function technologies(): TechnologyService
    {
        return app(TechnologyService::class);
    }

    private function facility(string $typeKey, string $name): Facility
    {
        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', $typeKey)->sole();

        return Facility::factory()->for($this->corporation)->for($type)->create(['name' => $name]);
    }

    /**
     * @param  array<string, int>  $points
     */
    private function givePoints(array $points): void
    {
        $trackers = app(TrackerService::class);

        foreach ($points as $suit => $amount) {
            $trackers->set($this->corporation, ResearchSuit::from($suit)->tracker(), $amount);
        }

        $this->corporation->refresh();
    }

    /**
     * @param  array<string, int>  $cost
     */
    private function technology(string $name, array $cost = [], array $attributes = []): TechnologyType
    {
        return TechnologyType::factory()->for($this->game)->create([
            'name' => $name,
            'cog_cost' => $cost['cog'] ?? 0,
            'brain_cost' => $cost['brain'] ?? 0,
            'leaf_cost' => $cost['leaf'] ?? 0,
            'maths_cost' => $cost['maths'] ?? 0,
            ...$attributes,
        ]);
    }

    public function test_researching_charges_the_points_and_houses_the_card(): void
    {
        $technology = $this->technology('Laser Porridge', ['brain' => 6, 'leaf' => 4]);

        $holding = $this->technologies()->research(
            $this->corporation,
            $technology,
            $this->research,
        );

        $this->assertSame(TechnologyHoldingStatus::Researched, $holding->status);
        $this->assertSame($this->research->id, $holding->facility_id);
        $this->assertSame(['cog' => 0, 'brain' => 6, 'leaf' => 4, 'maths' => 0], $holding->paid());

        $this->corporation->refresh();

        $this->assertSame(14, $this->corporation->brain_points);
        $this->assertSame(16, $this->corporation->leaf_points);

        // Through the ledger, like every other number that moves.
        $this->assertDatabaseHas('tracker_adjustments', [
            'subject_id' => $this->corporation->id,
            'tracker' => Tracker::ResearchBrain->value,
            'delta' => -6,
            'reason' => 'Researched Laser Porridge',
        ]);
    }

    public function test_a_corporation_that_cannot_pay_is_refused(): void
    {
        $technology = $this->technology('Laser Porridge', ['brain' => 60]);

        $this->expectException(ValidationException::class);

        $this->technologies()->research($this->corporation, $technology, $this->research);
    }

    public function test_prerequisites_have_to_be_researched_first(): void
    {
        $first = $this->technology('Enzyme upgrade', ['brain' => 2]);
        $second = $this->technology('Mutation corrections', ['brain' => 2], [
            'prerequisites' => ['Enzyme upgrade'],
        ]);

        $this->assertSame(
            ['Enzyme upgrade'],
            $this->technologies()->missingPrerequisites($this->corporation, $second),
        );

        $this->technologies()->research($this->corporation, $first, $this->research);

        $this->assertTrue($this->technologies()->prerequisitesMet($this->corporation, $second));
    }

    public function test_a_prerequisite_naming_a_split_technology_is_met_by_any_one_piece(): void
    {
        $piece = $this->technology('Power (Part 2/4)', [], [
            'split_group' => 'Power',
            'split_piece' => 2,
            'split_pieces' => 4,
        ]);

        $needsPower = $this->technology('Power economy', ['cog' => 2], [
            'prerequisites' => ['Power'],
        ]);

        $this->technologies()->research($this->corporation, $piece, $this->research);

        // 3.2.7: the Corporation that owns a split technology works it holding
        // any one of the pieces, so it has Power.
        $this->assertTrue($this->technologies()->prerequisitesMet($this->corporation, $needsPower));
    }

    public function test_a_technology_that_names_a_facility_type_can_only_be_housed_there(): void
    {
        /** @var FacilityType $security */
        $security = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::SECURITY)->sole();

        $technology = $this->technology('Roboscorpion mk2', ['cog' => 2], [
            'required_facility_type_id' => $security->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->technologies()->research($this->corporation, $technology, $this->research);
    }

    public function test_a_facility_belonging_to_another_corporation_is_refused(): void
    {
        $rival = Corporation::factory()->for($this->game)->create(['name' => 'Augmented Nucleotech']);

        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        $theirs = Facility::factory()->for($rival)->for($type)->create(['name' => 'Kelham Island']);

        $this->expectException(ValidationException::class);

        $this->technologies()->research(
            $this->corporation,
            $this->technology('Laser Porridge', ['cog' => 1]),
            $theirs,
        );
    }

    public function test_storage_is_two_for_every_corporate_facility(): void
    {
        $this->assertSame(2, $this->technologies()->capacityFor($this->research));

        $this->facility(FacilityTypeBlueprint::CORPORATE, 'Fitzalan Chambers');

        $this->assertSame(4, $this->technologies()->capacityFor($this->research->refresh()));
    }

    public function test_a_corporation_with_no_corporate_facility_can_store_nothing(): void
    {
        $this->corporate->delete();

        $this->expectException(ValidationException::class);

        $this->technologies()->research(
            $this->corporation,
            $this->technology('Laser Porridge', ['cog' => 1]),
            $this->research->refresh(),
        );
    }

    public function test_a_full_facility_is_refused(): void
    {
        // Capacity is two, and a claimed copy takes a slot just as a researched
        // card does (footnote 8 to 3.2.6).
        TechnologyHolding::factory()->count(2)->create([
            'game_id' => $this->game->id,
            'corporation_id' => $this->corporation->id,
            'technology_type_id' => $this->technology('Filler')->id,
            'facility_id' => $this->research->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->technologies()->research(
            $this->corporation,
            $this->technology('Laser Porridge', ['cog' => 1]),
            $this->research,
        );
    }

    public function test_a_discount_rounds_the_resulting_cost_up(): void
    {
        // "Rounding the resulting cost up" (3.2.6): a 5 at 50% off is 3.
        $technology = $this->technology('Laser Porridge', ['brain' => 5, 'leaf' => 3]);

        $this->assertSame(
            ['cog' => 0, 'brain' => 3, 'leaf' => 2, 'maths' => 0],
            $this->technologies()->costFor($technology, 50),
        );

        $this->assertSame(
            ['cog' => 0, 'brain' => 4, 'leaf' => 3, 'maths' => 0],
            $this->technologies()->costFor($technology, 25),
        );
    }

    public function test_paying_for_a_copy_flips_that_card_rather_than_making_a_second(): void
    {
        $technology = $this->technology('Laser Porridge', ['brain' => 5]);

        $claim = $this->technologies()->grant(
            $this->corporation,
            $technology,
            TechnologyOrigin::GoodCopy,
            facility: $this->research,
        );

        $this->assertSame(TechnologyHoldingStatus::Claimed, $claim->status);
        $this->assertSame(50, $claim->discount_percent);
        $this->assertFalse($this->technologies()->isUsable($claim));

        $researched = $this->technologies()->research(
            $this->corporation,
            $technology,
            $this->research,
            $claim,
        );

        $this->assertSame($claim->id, $researched->id);
        $this->assertSame(TechnologyHoldingStatus::Researched, $researched->status);
        // Half of five, rounded up.
        $this->assertSame(3, $researched->paid()['brain']);
        $this->assertSame(1, $this->corporation->technologyHoldings()->count());
        $this->assertSame(17, $this->corporation->refresh()->brain_points);
    }

    public function test_a_stolen_split_technology_does_nothing_until_every_piece_is_collected(): void
    {
        $pieces = [];

        foreach (range(1, 4) as $piece) {
            $pieces[$piece] = $this->technology("Power (Part {$piece}/4)", ['cog' => 1], [
                'split_group' => 'Power',
                'split_piece' => $piece,
                'split_pieces' => 4,
            ]);
        }

        $stolen = $this->technologies()->grant(
            $this->corporation,
            $pieces[1],
            TechnologyOrigin::Stolen,
            facility: $this->research,
        );

        $first = $this->technologies()->research(
            $this->corporation,
            $pieces[1],
            $this->research,
            $stolen,
        );

        // Researched and stored, and still doing nothing: a thief needs them
        // all (3.2.7).
        $this->assertTrue($first->isResearched());
        $this->assertFalse($this->technologies()->isUsable($first->refresh()));

        foreach ([2, 3, 4] as $piece) {
            $claim = $this->technologies()->grant(
                $this->corporation,
                $pieces[$piece],
                TechnologyOrigin::Stolen,
            );

            $this->technologies()->research(
                $this->corporation,
                $pieces[$piece],
                $piece === 2 ? $this->research : $this->corporate,
                $claim,
            );
        }

        $this->assertSame(4, $this->technologies()->piecesHeld($this->corporation, 'Power'));
        $this->assertTrue($this->technologies()->isUsable($first->refresh()));
    }

    public function test_the_corporation_that_researched_a_split_technology_works_it_on_one_piece(): void
    {
        $piece = $this->technology('Power (Part 3/4)', ['cog' => 1], [
            'split_group' => 'Power',
            'split_piece' => 3,
            'split_pieces' => 4,
        ]);

        $holding = $this->technologies()->research($this->corporation, $piece, $this->research);

        $this->assertTrue($this->technologies()->isUsable($holding));
    }

    public function test_destroying_a_card_keeps_the_row_and_frees_the_slot(): void
    {
        $technology = $this->technology('Laser Porridge', ['cog' => 1]);

        $holding = $this->technologies()->research($this->corporation, $technology, $this->research);

        $this->assertSame(1, $this->technologies()->storedIn($this->research));

        $this->technologies()->destroy($holding, 'Taken out by g33ks');

        $this->assertSame(TechnologyHoldingStatus::Destroyed, $holding->refresh()->status);
        $this->assertSame(0, $this->technologies()->storedIn($this->research));
        $this->assertFalse($this->technologies()->isUsable($holding));

        // And Control may let them salvage it.
        $this->technologies()->restore($holding);

        $this->assertTrue($this->technologies()->isUsable($holding->refresh()));
    }

    public function test_points_traded_between_corporations_move_both_ways_through_the_ledger(): void
    {
        $recipient = Corporation::factory()->for($this->game)->create(['name' => 'Genetic Equity']);

        $this->technologies()->transferPoints(
            $this->corporation,
            $recipient,
            ResearchSuit::Leaf,
            7,
        );

        $this->assertSame(13, $this->corporation->refresh()->leaf_points);
        $this->assertSame(7, $recipient->refresh()->leaf_points);

        $this->assertDatabaseHas('tracker_adjustments', [
            'subject_id' => $this->corporation->id,
            'tracker' => Tracker::ResearchLeaf->value,
            'delta' => -7,
            'reason' => 'Traded to Genetic Equity',
        ]);
        $this->assertDatabaseHas('tracker_adjustments', [
            'subject_id' => $recipient->id,
            'tracker' => Tracker::ResearchLeaf->value,
            'delta' => 7,
            'reason' => 'Traded from Gordon',
        ]);
    }

    public function test_a_corporation_cannot_trade_away_points_it_does_not_have(): void
    {
        $recipient = Corporation::factory()->for($this->game)->create(['name' => 'Genetic Equity']);

        $this->expectException(ValidationException::class);

        $this->technologies()->transferPoints(
            $this->corporation,
            $recipient,
            ResearchSuit::Leaf,
            100,
        );
    }

    public function test_a_technology_on_another_corporations_tree_is_refused(): void
    {
        $rival = Corporation::factory()->for($this->game)->create(['name' => 'Augmented Nucleotech']);

        $theirs = $this->technology('Amdumbla', ['cog' => 1], [
            'corporation_id' => $rival->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->technologies()->research($this->corporation, $theirs, $this->research);
    }

    public function test_the_tree_is_the_common_set_plus_your_own(): void
    {
        $rival = Corporation::factory()->for($this->game)->create(['name' => 'Augmented Nucleotech']);

        $common = $this->technology('Honey pot');
        $mine = $this->technology('Gordon special', [], ['corporation_id' => $this->corporation->id]);
        $theirs = $this->technology('Amdumbla', [], ['corporation_id' => $rival->id]);

        $ids = $this->technologies()->treeFor($this->corporation)->pluck('id');

        $this->assertTrue($ids->contains($common->id));
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }
}
