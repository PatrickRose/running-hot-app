<?php

namespace Tests\Feature;

use App\Models\Corporation;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\User;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * MCM's Construction Leader: one Corporation building a Facility for another,
 * with the owner charged and the builder paid.
 */
class FacilityBuiltOnBehalfTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $mcm;

    private Corporation $owner;

    private FacilityType $corporate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
        $this->mcm = Corporation::factory()->for($this->game)->create([
            'name' => 'McCullough',
            'credits' => 5,
            'facility_build_discount' => 2,
        ]);
        $this->owner = Corporation::factory()->for($this->game)->create(['credits' => 30]);
        $this->corporate = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::CORPORATE)->sole();

        app(TurnEngine::class)->start($this->game);
    }

    public function test_by_default_the_owner_pays_the_price_less_the_builders_discount_and_the_builder_is_paid_one(): void
    {
        $this->build()
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $facility = $this->owner->facilities()->sole();

        $this->assertSame('Sheffield Spire', $facility->name);
        $this->assertSame(2, $facility->available_from_turn);
        $this->assertSame(0, $this->mcm->facilities()->count());

        $this->assertSame(30 - (12 - 2), $this->owner->refresh()->credits);
        $this->assertSame(5 + 1, $this->mcm->refresh()->credits);

        $reasons = $this->game->trackerAdjustments()->orderBy('id')->pluck('reason');

        $this->assertStringContainsString('McCullough built Sheffield Spire', (string) $reasons[0]);
        $this->assertStringContainsString('for '.$this->owner->name, (string) $reasons[1]);
    }

    public function test_control_names_the_charge_and_the_fee(): void
    {
        $this->build(['cost' => 4, 'fee' => 3, 'mode' => 'immediate'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->owner->facilities()->sole()->available_from_turn);
        $this->assertSame(26, $this->owner->refresh()->credits);
        $this->assertSame(8, $this->mcm->refresh()->credits);
    }

    public function test_an_owner_who_cannot_pay_gets_nothing_and_the_builder_is_not_paid(): void
    {
        $this->owner->update(['credits' => 3]);

        $this->build()->assertSessionHasErrors('cost');

        $this->assertSame(0, $this->owner->facilities()->count());
        $this->assertSame(3, $this->owner->refresh()->credits);
        $this->assertSame(5, $this->mcm->refresh()->credits);
    }

    public function test_a_corporation_cannot_build_for_itself(): void
    {
        $this->build(['corporation_id' => $this->mcm->id])
            ->assertSessionHasErrors('builder_corporation_id');

        $this->assertSame(0, $this->mcm->facilities()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function build(array $overrides = []): TestResponse
    {
        return $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$this->game->id}/facilities/on-behalf", [
                'builder_corporation_id' => $this->mcm->id,
                'corporation_id' => $this->owner->id,
                'facility_type_id' => $this->corporate->id,
                'name' => 'Sheffield Spire',
                'mode' => 'requisition',
                ...$overrides,
            ]);
    }
}
