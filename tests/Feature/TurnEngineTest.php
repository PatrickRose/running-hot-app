<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Models\Game;
use App\Services\TurnEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class TurnEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function engine(): TurnEngine
    {
        return app(TurnEngine::class);
    }

    public function test_starting_a_game_opens_turn_one_setup(): void
    {
        $this->freezeTime();

        $game = Game::factory()->create(['setup_seconds' => 900]);

        $phase = $this->engine()->start($game);

        $this->assertSame(1, $phase->turn->number);
        $this->assertSame(PhaseType::Setup, $phase->type);
        $this->assertSame(PhaseStatus::Running, $phase->status);
        $this->assertSame(900, $phase->remainingSeconds());
        $this->assertSame(GameStatus::Running, $game->fresh()->status);
    }

    public function test_a_game_cannot_be_started_twice(): void
    {
        $game = Game::factory()->create();
        $this->engine()->start($game);

        $this->expectException(RuntimeException::class);

        $this->engine()->start($game->fresh());
    }

    public function test_phases_run_in_rulebook_order_and_roll_into_a_new_turn(): void
    {
        $game = Game::factory()->create();
        $engine = $this->engine();

        $setup = $engine->start($game);
        $this->assertSame(PhaseType::Setup, $setup->type);

        $action = $engine->advance($setup);
        $this->assertSame(PhaseType::Action, $action->type);
        $this->assertSame(1, $action->turn->number);

        $teamTime = $engine->advance($action);
        $this->assertSame(PhaseType::TeamTime, $teamTime->type);
        $this->assertSame(1, $teamTime->turn->number);

        $nextSetup = $engine->advance($teamTime);
        $this->assertSame(PhaseType::Setup, $nextSetup->type);
        $this->assertSame(2, $nextSetup->turn->number, 'Team Time should roll into the next turn.');

        $this->assertSame(PhaseStatus::Completed, $setup->fresh()->status);
    }

    public function test_each_phase_uses_its_own_configured_duration(): void
    {
        $this->freezeTime();

        $game = Game::factory()->create([
            'setup_seconds' => 600,
            'action_seconds' => 1200,
            'team_time_seconds' => 120,
        ]);
        $engine = $this->engine();

        $setup = $engine->start($game);
        $this->assertSame(600, $setup->remainingSeconds());

        $action = $engine->advance($setup);
        $this->assertSame(1200, $action->remainingSeconds());

        $teamTime = $engine->advance($action);
        $this->assertSame(120, $teamTime->remainingSeconds());
    }

    public function test_pausing_freezes_the_clock_and_resuming_returns_the_same_time(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $engine = $this->engine();

        $phase = $engine->start($game);

        Carbon::setTestNow('2026-08-08 12:05:00');
        $engine->pause($phase);

        $this->assertSame(600, $phase->fresh()->remainingSeconds());

        // Two minutes pass while the clock is stopped.
        Carbon::setTestNow('2026-08-08 12:07:00');
        $this->assertSame(600, $phase->fresh()->remainingSeconds(), 'A paused clock must not drain.');

        $engine->resume($phase->fresh());

        $this->assertSame(600, $phase->fresh()->remainingSeconds());
        $this->assertSame(PhaseStatus::Running, $phase->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_extending_adds_time_to_the_running_phase(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $phase = $this->engine()->start($game);

        $this->engine()->extend($phase, 300);

        $this->assertSame(1200, $phase->fresh()->remainingSeconds());

        Carbon::setTestNow();
    }

    public function test_extending_by_a_negative_amount_shortens_the_phase(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $phase = $this->engine()->start($game);

        $this->engine()->extend($phase, -300);

        $this->assertSame(600, $phase->fresh()->remainingSeconds());

        Carbon::setTestNow();
    }

    public function test_every_clock_mutation_bumps_the_version(): void
    {
        $game = Game::factory()->create();
        $engine = $this->engine();

        $phase = $engine->start($game);
        $this->assertSame(0, $phase->version);

        $engine->pause($phase);
        $this->assertSame(1, $phase->fresh()->version);

        $engine->resume($phase->fresh());
        $this->assertSame(2, $phase->fresh()->version);

        $engine->extend($phase->fresh(), 60);
        $this->assertSame(3, $phase->fresh()->version);
    }

    public function test_a_completed_phase_cannot_be_advanced_again(): void
    {
        $game = Game::factory()->create();
        $engine = $this->engine();

        $phase = $engine->start($game);
        $engine->advance($phase);

        $this->expectException(RuntimeException::class);

        $engine->advance($phase->fresh());
    }

    public function test_finishing_a_game_closes_the_open_phase(): void
    {
        $game = Game::factory()->create();
        $engine = $this->engine();

        $phase = $engine->start($game);
        $engine->finish($game->fresh());

        $this->assertSame(GameStatus::Finished, $game->fresh()->status);
        $this->assertSame(PhaseStatus::Completed, $phase->fresh()->status);
        $this->assertNull($game->fresh()->currentPhase());
    }
}
