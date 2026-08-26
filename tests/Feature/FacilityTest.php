<?php

namespace Tests\Feature;

use App\Actions\RequisitionFacility;
use App\Enums\FacilityGrantScaling;
use App\Enums\PhaseType;
use App\Enums\ProtectionKind;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\User;
use App\Services\FacilityDefenceService;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FacilityTest extends TestCase
{
    use RefreshDatabase;

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    public function test_a_new_game_starts_with_the_whole_type_sheet(): void
    {
        $game = Game::factory()->create();

        $this->assertSame(
            array_column(FacilityTypeBlueprint::defaults(), 'key'),
            $game->facilityTypes()->orderBy('id')->pluck('key')->all(),
        );
    }

    public function test_each_type_carries_its_build_cost(): void
    {
        $game = Game::factory()->create();

        $costs = $game->facilityTypes()->pluck('build_cost', 'key');

        $this->assertSame(5, $costs[FacilityTypeBlueprint::SECURITY]);
        $this->assertSame(12, $costs[FacilityTypeBlueprint::CORPORATE]);
        $this->assertSame(8, $costs[FacilityTypeBlueprint::RESEARCH]);
        $this->assertSame(20, $costs[FacilityTypeBlueprint::MISSILE_NETWORK]);
    }

    public function test_the_types_that_step_are_marked_as_stepping(): void
    {
        $game = Game::factory()->create();

        $scaling = $game->facilityTypes()->pluck('grant_scaling', 'key');

        // "an additional ... at 2, 3, 5, 8 etc Facilities" on the type sheet.
        $this->assertSame(
            FacilityGrantScaling::Thresholds,
            $scaling[FacilityTypeBlueprint::FACTORY],
        );
        $this->assertSame(
            FacilityGrantScaling::PerFacility,
            $scaling[FacilityTypeBlueprint::SECURITY],
        );
    }

    public function test_the_security_type_grants_a_slot_and_the_corporate_type_grants_storage(): void
    {
        $game = Game::factory()->create();

        $security = $game->facilityTypes()->where('key', FacilityTypeBlueprint::SECURITY)->sole();
        $corporate = $game->facilityTypes()->where('key', FacilityTypeBlueprint::CORPORATE)->sole();
        $research = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        // Asymmetric, from the game's type sheet: 1 physical, 2 cyber.
        $this->assertSame(1, $security->physical_slots_granted);
        $this->assertSame(2, $security->cyber_slots_granted);
        $this->assertSame(0, $security->technology_capacity_granted);
        $this->assertSame(2, $corporate->technology_capacity_granted);
        $this->assertSame(0, $research->physical_slots_granted);
        $this->assertSame(0, $research->cyber_slots_granted);
    }

    public function test_control_can_add_a_facility_type_mid_game(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/facility-types", [
                'name' => 'Fabrication',
                'description' => 'Houses prototypes that cannot leave the line.',
                'physical_slots_granted' => 0,
                'cyber_slots_granted' => 0,
                'technology_capacity_granted' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $type = $game->facilityTypes()->where('key', 'fabrication')->sole();

        $this->assertSame('Fabrication', $type->name);
        $this->assertSame(3, $type->technology_capacity_granted);
    }

    public function test_a_facility_type_name_cannot_be_reused_within_a_game(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/facility-types", ['name' => 'Security'])
            ->assertSessionHasErrors('name');
    }

    public function test_two_games_keep_separate_catalogues(): void
    {
        $first = Game::factory()->create();
        $second = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$first->id}/facility-types", ['name' => 'Fabrication'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($first->facilityTypes()->where('name', 'Fabrication')->exists());
        $this->assertFalse($second->facilityTypes()->where('name', 'Fabrication')->exists());
    }

    public function test_a_facility_type_in_use_cannot_be_deleted(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        Facility::factory()->for($corporation)->for($type)->create();

        $this->actingAs($this->control())
            ->delete("/control/games/{$game->id}/facility-types/{$type->id}")
            ->assertSessionHasErrors('facility_type_id');

        $this->assertTrue($type->exists());
    }

    public function test_an_unused_facility_type_can_be_deleted(): void
    {
        $game = Game::factory()->create();
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        $this->actingAs($this->control())
            ->delete("/control/games/{$game->id}/facility-types/{$type->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('facility_types', ['id' => $type->id]);
    }

    public function test_a_requisitioned_facility_opens_next_turn_and_costs_credits(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['credits' => 30]);
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::SECURITY)->sole();

        app(TurnEngine::class)->start($game);

        $facility = app(RequisitionFacility::class)->handle($corporation, $type, 'Attercliffe Yard', 10);

        $this->assertSame(2, $facility->available_from_turn);
        $this->assertFalse($facility->isAvailableOnTurn(1));
        $this->assertTrue($facility->isAvailableOnTurn(2));
        $this->assertSame(20, $corporation->fresh()->credits);
    }

    public function test_the_build_cost_is_written_to_the_ledger(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['credits' => 30]);
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::SECURITY)->sole();

        app(TurnEngine::class)->start($game);
        app(RequisitionFacility::class)->handle($corporation, $type, 'Attercliffe Yard', 10);

        $adjustment = $game->trackerAdjustments()->latest('id')->sole();

        $this->assertSame(-10, $adjustment->delta);
        $this->assertStringContainsString('Attercliffe Yard', (string) $adjustment->reason);
    }

    public function test_a_requisition_outside_setup_is_refused(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['credits' => 30]);
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::SECURITY)->sole();

        $engine = app(TurnEngine::class);
        $phase = $engine->start($game);
        $engine->advance($phase);

        $this->assertSame(PhaseType::Action, $game->fresh()->currentPhase()?->type);

        $this->expectException(ValidationException::class);

        app(RequisitionFacility::class)->handle($corporation, $type, 'Attercliffe Yard', 10);
    }

    public function test_a_corporation_that_cannot_pay_does_not_get_a_facility(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['credits' => 2]);
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::SECURITY)->sole();

        app(TurnEngine::class)->start($game);

        try {
            app(RequisitionFacility::class)->handle($corporation, $type, 'Attercliffe Yard', 10);
            $this->fail('Expected the requisition to be refused.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(0, $corporation->facilities()->count());
        $this->assertSame(2, $corporation->fresh()->credits);
    }

    public function test_control_can_build_a_facility_immediately(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['credits' => 30]);
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::CORPORATE)->sole();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/facilities", [
                'corporation_id' => $corporation->id,
                'facility_type_id' => $type->id,
                'name' => 'Sheffield Spire',
                'mode' => 'immediate',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $facility = $corporation->facilities()->sole();

        $this->assertSame(1, $facility->available_from_turn);

        // The type sheet's price for a Corporate Facility, charged because
        // Control named no other.
        $this->assertSame(30 - 12, $corporation->fresh()->credits);
    }

    public function test_control_can_name_a_price_other_than_the_type_sheets(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['credits' => 30]);
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::CORPORATE)->sole();

        // MCM's Construction Leader technology is a discount on exactly this.
        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/facilities", [
                'corporation_id' => $corporation->id,
                'facility_type_id' => $type->id,
                'name' => 'Sheffield Spire',
                'mode' => 'immediate',
                'cost' => 9,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(21, $corporation->fresh()->credits);
    }

    public function test_control_can_bring_a_building_facility_forward(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();
        $facility = Facility::factory()->for($corporation)->for($type)->buildingUntilTurn(4)->create();

        $this->actingAs($this->control())
            ->patch("/control/games/{$game->id}/facilities/{$facility->id}", [
                'available_from_turn' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $facility->fresh()->available_from_turn);
    }

    public function test_a_facility_name_is_unique_within_a_corporation(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();
        Facility::factory()->for($corporation)->for($type)->create(['name' => 'Attercliffe Yard']);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/facilities", [
                'corporation_id' => $corporation->id,
                'facility_type_id' => $type->id,
                'name' => 'Attercliffe Yard',
                'mode' => 'immediate',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_a_facility_type_from_another_game_is_refused(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();
        $other = FacilityType::factory()->for(Game::factory())->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/facilities", [
                'corporation_id' => $corporation->id,
                'facility_type_id' => $other->id,
                'name' => 'Attercliffe Yard',
                'mode' => 'immediate',
            ])
            ->assertSessionHasErrors('facility_type_id');
    }

    public function test_control_can_change_what_a_facility_type_grants(): void
    {
        $game = Game::factory()->create();
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        $this->actingAs($this->control())
            ->patch("/control/games/{$game->id}/facility-types/{$type->id}", [
                'name' => 'Research and Development',
                'physical_slots_granted' => 1,
                'cyber_slots_granted' => 1,
                'technology_capacity_granted' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $type = $type->fresh();

        $this->assertSame('Research and Development', $type?->name);
        $this->assertSame(1, $type?->physical_slots_granted);

        // The key is left alone, so anything keying off it still resolves.
        $this->assertSame(FacilityTypeBlueprint::RESEARCH, $type?->key);
    }

    public function test_a_renamed_type_widens_the_stacks_it_now_grants_slots_for(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();
        Facility::factory()->for($corporation)->for($type)->create();

        $defence = app(FacilityDefenceService::class);

        $this->assertSame(3, $defence->slotsPerKind($corporation, ProtectionKind::Physical));

        $type->forceFill(['physical_slots_granted' => 2])->save();

        $this->assertSame(5, $defence->slotsPerKind($corporation->fresh(), ProtectionKind::Physical));
    }

    public function test_control_can_remove_a_facility(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();
        $facility = Facility::factory()->for($corporation)->for($type)->create();

        $this->actingAs($this->control())
            ->delete("/control/games/{$game->id}/facilities/{$facility->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
    }

    public function test_a_facility_from_another_game_is_not_reachable(): void
    {
        $game = Game::factory()->create();
        $other = Game::factory()->create();
        $corporation = Corporation::factory()->for($other)->create();
        $type = $other->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();
        $facility = Facility::factory()->for($corporation)->for($type)->create();

        $this->actingAs($this->control())
            ->delete("/control/games/{$game->id}/facilities/{$facility->id}")
            ->assertNotFound();
    }

    public function test_control_can_open_the_facilities_panel(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->get("/control/games/{$game->id}/facilities")
            ->assertOk();
    }

    public function test_a_player_cannot_reach_the_facilities_panel(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/control/games/{$game->id}/facilities")
            ->assertForbidden();
    }
}
