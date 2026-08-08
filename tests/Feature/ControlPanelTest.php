<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\User;
use App\Services\TurnEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    public function test_a_player_cannot_reach_the_control_panel(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/control/games/{$game->id}")
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $game = Game::factory()->create();

        $this->get("/control/games/{$game->id}")->assertRedirect('/login');
    }

    public function test_control_can_open_the_dashboard(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->get("/control/games/{$game->id}")
            ->assertOk();
    }

    public function test_control_can_start_and_advance_the_clock(): void
    {
        $game = Game::factory()->create();
        $user = $this->control();

        $this->actingAs($user)
            ->post("/control/games/{$game->id}/phase/start")
            ->assertRedirect();

        $phase = $game->fresh()->currentPhase();
        $this->assertNotNull($phase);
        $this->assertSame(PhaseType::Setup, $phase->type);

        $this->actingAs($user)
            ->post("/control/games/{$game->id}/phase/advance")
            ->assertRedirect();

        $this->assertSame(PhaseType::Action, $game->fresh()->currentPhase()?->type);
    }

    public function test_control_can_pause_and_resume(): void
    {
        $game = Game::factory()->create();
        $user = $this->control();
        app(TurnEngine::class)->start($game);

        $this->actingAs($user)->post("/control/games/{$game->id}/phase/pause");
        $this->assertSame(PhaseStatus::Paused, $game->fresh()->currentPhase()?->status);

        $this->actingAs($user)->post("/control/games/{$game->id}/phase/resume");
        $this->assertSame(PhaseStatus::Running, $game->fresh()->currentPhase()?->status);
    }

    public function test_extending_requires_a_non_zero_amount(): void
    {
        $game = Game::factory()->create();
        app(TurnEngine::class)->start($game);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/phase/extend", ['seconds' => 0])
            ->assertSessionHasErrors('seconds');
    }

    public function test_advancing_a_game_that_has_not_started_is_a_validation_error(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/phase/advance")
            ->assertSessionHasErrors('phase');
    }

    public function test_control_can_adjust_a_tracker(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['political_will' => 5]);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/trackers", [
                'subject_type' => 'corporation',
                'subject_id' => $corporation->id,
                'tracker' => 'political_will',
                'mode' => 'adjust',
                'value' => -2,
                'reason' => 'Reneged on a deal',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $corporation->fresh()->political_will);
    }

    public function test_a_tracker_cannot_be_applied_to_a_subject_it_does_not_belong_to(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/trackers", [
                'subject_type' => 'corporation',
                'subject_id' => $corporation->id,
                'tracker' => 'notoriety',
                'mode' => 'adjust',
                'value' => 1,
            ])
            ->assertSessionHasErrors('tracker');
    }

    public function test_a_tracker_cannot_target_a_subject_from_another_game(): void
    {
        $game = Game::factory()->create();
        $other = Corporation::factory()->for(Game::factory()->create())->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/trackers", [
                'subject_type' => 'corporation',
                'subject_id' => $other->id,
                'tracker' => 'income',
                'mode' => 'set',
                'value' => 999,
            ])
            ->assertSessionHasErrors('subject_id');

        $this->assertNotSame(999, $other->fresh()->income);
    }

    public function test_a_player_cannot_adjust_trackers(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['income' => 1]);

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/trackers", [
                'subject_type' => 'corporation',
                'subject_id' => $corporation->id,
                'tracker' => 'income',
                'mode' => 'set',
                'value' => 500,
            ])
            ->assertForbidden();

        $this->assertSame(1, $corporation->fresh()->income);
    }

    public function test_a_tag_costs_three_credits_during_team_time(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create([
            'tags' => 2,
            'credits' => 10,
        ]);

        $engine = app(TurnEngine::class);
        $engine->advance($engine->advance($engine->start($game)));

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/characters/{$character->id}/remove-tag")
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $character->fresh()->tags);
        $this->assertSame(7, $character->fresh()->credits);
    }

    public function test_a_tag_cannot_be_bought_off_outside_team_time(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create(['tags' => 1, 'credits' => 10]);

        app(TurnEngine::class)->start($game);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/characters/{$character->id}/remove-tag")
            ->assertSessionHasErrors('tags');

        $this->assertSame(1, $character->fresh()->tags);
    }

    public function test_a_tag_cannot_be_bought_off_without_the_credits(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create(['tags' => 1, 'credits' => 2]);

        $engine = app(TurnEngine::class);
        $engine->advance($engine->advance($engine->start($game)));

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/characters/{$character->id}/remove-tag")
            ->assertSessionHasErrors('tags');

        $this->assertSame(1, $character->fresh()->tags);
        $this->assertSame(2, $character->fresh()->credits);
    }

    public function test_control_can_create_a_game(): void
    {
        $this->actingAs($this->control())
            ->post('/control/games', [
                'name' => 'Running Hot — Sheffield',
                'setup_seconds' => 600,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('games', [
            'name' => 'Running Hot — Sheffield',
            'setup_seconds' => 600,
        ]);
    }
}
