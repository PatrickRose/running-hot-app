<?php

namespace Tests\Feature;

use App\Enums\EquipmentCategory;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\User;
use App\Support\FacilityTypeBlueprint;
use App\Support\TechnologyBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Control adding to the Equipment list and the tech trees during play.
 *
 * Both are asked for by the rulebook rather than being a convenience. Custom
 * Technologies (3.2.4) has players writing research proposals that Research
 * Control prices and adds to the tree on the night, and a DTC technology hands
 * out a single-use bypass card named after whichever Protection Card it
 * counters - a card that has never been printed.
 */
class CardCatalogueEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    /**
     * @return array<string, mixed>
     */
    protected function equipmentPayload(array $overrides = []): array
    {
        return [
            'name' => 'Roboscorpion bypass',
            'category' => 'single-use',
            'effect' => 'Automatically succeed against a Roboscorpion protection card',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function technologyPayload(array $overrides = []): array
    {
        return [
            'name' => 'Laser Porridge',
            'tree' => TechnologyBlueprint::COMMON,
            'description' => 'Test whether a laser could be used to heat up porridge safely',
            'effect' => 'You can sell self heating porridge. Increase income',
            'cog_cost' => 0,
            'brain_cost' => 10,
            'leaf_cost' => 8,
            'maths_cost' => 0,
            ...$overrides,
        ];
    }

    public function test_control_can_add_an_equipment_card(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/equipment-cards", $this->equipmentPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $card = $game->equipmentCardTypes()->where('name', 'Roboscorpion bypass')->sole();

        $this->assertSame(EquipmentCategory::SingleUse, $card->category);
        // A card Control invents has no code, so no artwork, so it shows as its
        // own text.
        $this->assertNull($card->code);
        $this->assertNull($card->imagePath());
        // And no price, because the market is not modelled.
        $this->assertFalse($card->isOnSale());
    }

    public function test_an_equipment_code_is_unique_within_a_game(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/equipment-cards", $this->equipmentPayload([
                'code' => 'EEP002',
            ]))
            ->assertSessionHasErrors('code');
    }

    public function test_control_can_remove_an_equipment_card(): void
    {
        $game = Game::factory()->create();
        $card = $game->equipmentCardTypes()->where('code', 'EEP002')->sole();

        $this->actingAs($this->control())
            ->delete("/control/games/{$game->id}/equipment-cards/{$card->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($card->fresh());
    }

    public function test_control_can_add_a_technology(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/technologies", $this->technologyPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $technology = $game->technologyTypes()->where('name', 'Laser Porridge')->sole();

        $this->assertSame(
            ['cog' => 0, 'brain' => 10, 'leaf' => 8, 'maths' => 0],
            $technology->cost(),
        );
        $this->assertTrue($technology->isCommon());
        $this->assertSame([], $technology->prerequisites);
    }

    /**
     * Prerequisites are the titles printed on the cards, typed as one line: a
     * proposal may name research that does not exist yet, so they cannot be
     * foreign keys.
     */
    public function test_prerequisites_are_split_into_titles(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/technologies", $this->technologyPayload([
                'prerequisites' => 'Deadly snake; Snake woman ;; Fire dogs',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Deadly snake', 'Snake woman', 'Fire dogs'],
            $game->technologyTypes()->where('name', 'Laser Porridge')->sole()->prerequisites,
        );
    }

    public function test_a_technology_can_be_put_on_a_corporations_tree(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/technologies", $this->technologyPayload([
                'tree' => TechnologyBlueprint::GORDON,
                'corporation_id' => $corporation->id,
            ]))
            ->assertSessionHasNoErrors();

        $technology = $game->technologyTypes()->where('name', 'Laser Porridge')->sole();

        $this->assertSame($corporation->id, $technology->corporation_id);
        $this->assertFalse($technology->isCommon());
    }

    public function test_a_technology_can_require_a_facility_type(): void
    {
        $game = Game::factory()->create();
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/technologies", $this->technologyPayload([
                'required_facility_type_id' => $type->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'Research',
            $game->technologyTypes()->where('name', 'Laser Porridge')->sole()
                ->requiredFacilityType?->name,
        );
    }

    /**
     * A Corporation or Facility type from another game would put a technology on
     * a tree this game cannot see.
     */
    public function test_a_facility_type_from_another_game_is_refused(): void
    {
        $game = Game::factory()->create();
        $other = Game::factory()->create();
        $type = $other->facilityTypes()->first();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/technologies", $this->technologyPayload([
                'required_facility_type_id' => $type?->id,
            ]))
            ->assertSessionHasErrors('required_facility_type_id');
    }

    public function test_control_can_remove_a_technology(): void
    {
        $game = Game::factory()->create();
        $technology = $game->technologyTypes()->where('code', 'RSR001')->sole();

        $this->actingAs($this->control())
            ->delete("/control/games/{$game->id}/technologies/{$technology->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($technology->fresh());
    }

    public function test_a_card_from_another_game_cannot_be_removed(): void
    {
        $game = Game::factory()->create();
        $other = Game::factory()->create();
        $technology = $other->technologyTypes()->where('code', 'RSR001')->sole();

        $this->actingAs($this->control())
            ->delete("/control/games/{$game->id}/technologies/{$technology->id}")
            ->assertNotFound();

        $this->assertNotNull($technology->fresh());
    }

    public function test_a_player_cannot_edit_either_list(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/equipment-cards", $this->equipmentPayload())
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/technologies", $this->technologyPayload())
            ->assertForbidden();
    }
}
