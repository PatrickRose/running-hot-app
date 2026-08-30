<?php

namespace Tests\Feature;

use App\Enums\ProtectionKind;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\User;
use App\Services\FacilityDefenceService;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The card catalogue Control extends (rulebook 3.3.2, 3.3.3).
 */
class ProtectionCardCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    /**
     * @return array<string, mixed>
     */
    protected function cardPayload(array $overrides = []): array
    {
        return [
            'name' => 'Reinforced Bulkhead',
            'kind' => 'physical',
            'cost' => 4,
            'challenge' => 'Brute (3)',
            'consequence' => 'One Wound.',
            'availability' => 'available',
            ...$overrides,
        ];
    }

    public function test_control_can_add_a_card_to_the_catalogue(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/protection-cards", $this->cardPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $card = $game->protectionCardTypes()->where('name', 'Reinforced Bulkhead')->sole();

        $this->assertSame(ProtectionKind::Physical, $card->kind);
        $this->assertSame(4, $card->cost);
        // Held as the card prints it, rather than as a skill and a number.
        $this->assertSame('Brute (3)', $card->challenge);
        $this->assertFalse($card->hasCharge());
        // Control's own card has no code, so it has no artwork either and is
        // shown as its text.
        $this->assertNull($card->code);
        $this->assertNull($card->imagePath());
    }

    public function test_a_card_can_carry_a_charge(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/protection-cards", $this->cardPayload([
                'charge_cost' => 2,
                'charge_consequence' => 'One Tag.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            $game->protectionCardTypes()->where('name', 'Reinforced Bulkhead')->sole()->hasCharge(),
        );
    }

    public function test_a_charge_cost_without_a_consequence_is_refused(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/protection-cards", $this->cardPayload([
                'charge_cost' => 2,
            ]))
            ->assertSessionHasErrors('charge_consequence');
    }

    public function test_a_charge_consequence_without_a_cost_is_refused(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/protection-cards", $this->cardPayload([
                'charge_consequence' => 'One Tag.',
            ]))
            ->assertSessionHasErrors('charge_cost');
    }

    public function test_a_card_code_is_unique_within_a_game(): void
    {
        $game = Game::factory()->create();
        ProtectionCardType::factory()->for($game)->create(['code' => 'PZ001']);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/protection-cards", $this->cardPayload([
                'code' => 'PZ001',
            ]))
            ->assertSessionHasErrors('code');
    }

    /**
     * Doppleganger is PX011 in the physical stack and PX012 in the cyber one:
     * two different cards that share a title. So a repeated title has to be
     * allowed, and the code is what may not repeat.
     */
    public function test_two_cards_may_share_a_title(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/protection-cards", $this->cardPayload([
                'code' => 'PZ001',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/protection-cards", $this->cardPayload([
                'code' => 'PZ002',
                'kind' => 'cyber',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            2,
            $game->protectionCardTypes()->where('name', 'Reinforced Bulkhead')->count(),
        );
    }

    public function test_control_can_promote_a_rumoured_card(): void
    {
        $game = Game::factory()->create();
        $card = ProtectionCardType::factory()->for($game)->create(['availability' => 'rumoured']);

        $this->actingAs($this->control())
            ->patch("/control/games/{$game->id}/protection-cards/{$card->id}", $this->cardPayload([
                'code' => $card->code,
                'name' => $card->name,
                'availability' => 'available',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('available', $card->fresh()?->availability->value);
    }

    public function test_an_installed_card_cannot_change_kind(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();
        /** @var FacilityType $type */
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();
        $facility = Facility::factory()->for($corporation)->for($type)->create();
        $card = ProtectionCardType::factory()->for($game)->heldBy($corporation->id)->create();

        app(FacilityDefenceService::class)->install($facility, $card);

        $this->actingAs($this->control())
            ->patch("/control/games/{$game->id}/protection-cards/{$card->id}", $this->cardPayload([
                'code' => $card->code,
                'name' => $card->name,
                'kind' => 'cyber',
            ]))
            ->assertSessionHasErrors('kind');
    }

    public function test_an_installed_card_cannot_be_deleted(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();
        /** @var FacilityType $type */
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();
        $facility = Facility::factory()->for($corporation)->for($type)->create();
        $card = ProtectionCardType::factory()->for($game)->heldBy($corporation->id)->create();

        app(FacilityDefenceService::class)->install($facility, $card);

        $this->actingAs($this->control())
            ->delete("/control/games/{$game->id}/protection-cards/{$card->id}")
            ->assertSessionHasErrors('protection_card_type_id');
    }

    public function test_a_player_cannot_edit_the_catalogue(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/protection-cards", $this->cardPayload())
            ->assertForbidden();
    }
}
