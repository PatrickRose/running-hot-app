<?php

namespace Tests\Feature;

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

/**
 * Directing Security and the per-Facility budget (rulebook 3.3.5).
 */
class SecurityDirectionTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    private Facility $first;

    private Facility $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
        $this->corporation = Corporation::factory()->for($this->game)->create([
            'credits' => 40,
            'income' => 0,
        ]);
        $this->first = $this->facility('Attercliffe Yard');
        $this->second = $this->facility('Wicker Post');
    }

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    protected function facility(string $name): Facility
    {
        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        return Facility::factory()
            ->for($this->corporation)
            ->for($type)
            ->create(['name' => $name]);
    }

    protected function defence(): FacilityDefenceService
    {
        return app(FacilityDefenceService::class);
    }

    public function test_directing_security_at_one_facility_lifts_it_from_another(): void
    {
        app(TurnEngine::class)->start($this->game);
        $turn = $this->game->currentTurn();
        $this->assertNotNull($turn);

        $this->defence()->directSecurity($this->first, true, $turn);
        $this->assertTrue($this->first->stateForTurn($turn)->fresh()?->security_directed);

        $this->defence()->directSecurity($this->second, true, $turn);

        $this->assertFalse($this->first->stateForTurn($turn)->fresh()?->security_directed);
        $this->assertTrue($this->second->stateForTurn($turn)->fresh()?->security_directed);
    }

    public function test_another_corporation_keeps_its_own_meeple(): void
    {
        app(TurnEngine::class)->start($this->game);
        $turn = $this->game->currentTurn();
        $this->assertNotNull($turn);

        $rival = Corporation::factory()->for($this->game)->create();
        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();
        $theirs = Facility::factory()->for($rival)->for($type)->create();

        $this->defence()->directSecurity($theirs, true, $turn);
        $this->defence()->directSecurity($this->first, true, $turn);

        $this->assertTrue($theirs->stateForTurn($turn)->fresh()?->security_directed);
        $this->assertTrue($this->first->stateForTurn($turn)->fresh()?->security_directed);
    }

    public function test_placing_a_budget_takes_the_credits_now(): void
    {
        app(TurnEngine::class)->start($this->game);
        $turn = $this->game->currentTurn();
        $this->assertNotNull($turn);

        $this->defence()->setSecurityBudget($this->first, 10, $turn);

        $this->assertSame(30, $this->corporation->fresh()->credits);
        $this->assertSame(10, $this->first->stateForTurn($turn)->fresh()?->security_budget);
    }

    public function test_lowering_a_budget_hands_the_difference_back(): void
    {
        app(TurnEngine::class)->start($this->game);
        $turn = $this->game->currentTurn();
        $this->assertNotNull($turn);

        $this->defence()->setSecurityBudget($this->first, 10, $turn);
        $this->defence()->setSecurityBudget($this->first, 4, $turn);

        $this->assertSame(36, $this->corporation->fresh()->credits);
    }

    public function test_a_budget_cannot_be_cut_below_what_has_been_spent(): void
    {
        app(TurnEngine::class)->start($this->game);
        $turn = $this->game->currentTurn();
        $this->assertNotNull($turn);

        $this->defence()->setSecurityBudget($this->first, 10, $turn);
        $this->first->stateForTurn($turn)->forceFill(['security_budget_spent' => 6])->save();

        $this->expectException(ValidationException::class);

        $this->defence()->setSecurityBudget($this->first, 4, $turn);
    }

    public function test_a_corporation_cannot_place_a_budget_it_cannot_afford(): void
    {
        app(TurnEngine::class)->start($this->game);
        $turn = $this->game->currentTurn();
        $this->assertNotNull($turn);

        $this->expectException(ValidationException::class);

        $this->defence()->setSecurityBudget($this->first, 100, $turn);
    }

    public function test_unspent_budget_comes_back_when_the_action_phase_ends(): void
    {
        $engine = app(TurnEngine::class);
        $setup = $engine->start($this->game);
        $turn = $this->game->currentTurn();
        $this->assertNotNull($turn);

        $this->defence()->setSecurityBudget($this->first, 10, $turn);
        $this->defence()->setSecurityBudget($this->second, 6, $turn);
        $this->assertSame(24, $this->corporation->fresh()->credits);

        // 4 of the first Facility's budget is spent activating cards.
        $this->first->stateForTurn($turn)->forceFill(['security_budget_spent' => 4])->save();

        $action = $engine->advance($setup);
        $engine->advance($action);

        // 6 unspent on the first, 6 unspent on the second.
        $this->assertSame(36, $this->corporation->fresh()->credits);
        $this->assertNotNull($this->first->stateForTurn($turn)->fresh()?->budget_returned_at);
    }

    public function test_the_returned_budget_is_written_to_the_ledger(): void
    {
        $engine = app(TurnEngine::class);
        $setup = $engine->start($this->game);
        $turn = $this->game->currentTurn();
        $this->assertNotNull($turn);

        $this->defence()->setSecurityBudget($this->first, 10, $turn);

        $action = $engine->advance($setup);
        $engine->advance($action);

        $adjustment = $this->game->trackerAdjustments()
            ->where('delta', 10)
            ->latest('id')
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertTrue($adjustment->automated);
        $this->assertStringContainsString('unspent security budget', (string) $adjustment->reason);
    }

    public function test_a_budget_is_only_returned_once(): void
    {
        $engine = app(TurnEngine::class);
        $setup = $engine->start($this->game);
        $turn = $this->game->currentTurn();
        $this->assertNotNull($turn);

        $this->defence()->setSecurityBudget($this->first, 10, $turn);

        $action = $engine->advance($setup);
        $engine->advance($action);
        $this->assertSame(40, $this->corporation->fresh()->credits);

        $this->defence()->returnUnspentBudgets($turn);

        $this->assertSame(40, $this->corporation->fresh()->credits);
    }

    public function test_a_budget_left_over_from_an_earlier_turn_is_still_returned(): void
    {
        $engine = app(TurnEngine::class);
        $phase = $engine->start($this->game);
        $firstTurn = $this->game->currentTurn();
        $this->assertNotNull($firstTurn);

        // Setup, Action, Team Time - so the first turn's Action phase has
        // already closed by the time the budget is placed.
        $phase = $engine->advance($phase);
        $phase = $engine->advance($phase);

        $this->defence()->setSecurityBudget($this->first, 10, $firstTurn);
        $this->assertSame(30, $this->corporation->fresh()->credits);

        // Into turn 2, then through its Action phase.
        $phase = $engine->advance($phase);
        $phase = $engine->advance($phase);
        $engine->advance($phase);

        $this->assertSame(40, $this->corporation->fresh()->credits);
    }

    public function test_control_can_set_the_orders_over_http(): void
    {
        app(TurnEngine::class)->start($this->game);

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/facilities/{$this->first->id}/security", [
                'security_directed' => true,
                'security_budget' => 8,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $turn = $this->game->fresh()->currentTurn();
        $this->assertNotNull($turn);

        $state = $this->first->stateForTurn($turn)->fresh();
        $this->assertTrue($state?->security_directed);
        $this->assertSame(8, $state?->security_budget);
        $this->assertSame(32, $this->corporation->fresh()->credits);
    }

    public function test_orders_before_the_clock_starts_are_refused(): void
    {
        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/facilities/{$this->first->id}/security", [
                'security_directed' => true,
            ])
            ->assertSessionHasErrors('security_directed');
    }
}
