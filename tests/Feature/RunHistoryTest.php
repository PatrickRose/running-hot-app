<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\Gang;
use App\Models\Run;
use App\Models\RunEvent;
use App\Models\RunParticipant;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Control reading back every run that has ended, whatever turn it was on.
 *
 * The run screen only ever shows the current turn, so without this a run from
 * two turns ago could not be looked at at all.
 */
class RunHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
    }

    public function test_control_reads_every_ended_run_newest_turn_first(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create(['name' => 'Test History Combine']);
        $facility = Facility::factory()->for($corporation)->create(['name' => 'Test Yard']);
        $gang = Gang::factory()->for($this->game)->create(['name' => 'Test History Crew']);

        $first = Turn::factory()->for($this->game)->create(['number' => 1]);
        $second = Turn::factory()->for($this->game)->create(['number' => 2]);

        $leader = Character::factory()->runner($gang)->create(['game_id' => $this->game->id, 'name' => 'Wicker']);
        $walked = Character::factory()->runner($gang)->create(['game_id' => $this->game->id, 'name' => 'Ghost']);

        $succeeded = Run::factory()->succeeded()->create([
            'facility_id' => $facility->id,
            'turn_id' => $first->id,
            'run_leader_character_id' => $leader->id,
            'cards_passed' => 3,
        ]);
        RunParticipant::factory()->for($succeeded)->create(['character_id' => $leader->id, 'position' => 1]);
        RunParticipant::factory()->for($succeeded)->left()->create(['character_id' => $walked->id, 'position' => 2]);
        RunEvent::factory()->for($succeeded)->count(6)->create();

        $failed = Run::factory()->failed()->create(['facility_id' => $facility->id, 'turn_id' => $second->id]);

        // Still going, so it belongs on the run screen rather than here.
        Run::factory()->running()->create(['facility_id' => $facility->id, 'turn_id' => $second->id]);

        // Another game's history is nothing to do with this one.
        Run::factory()->succeeded()->create();

        $this->actingAs($this->control())
            ->get("/control/games/{$this->game->id}/runs")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('control/games/runs')
                ->has('turns', 2)
                ->where('turns.0.number', 2)
                ->has('turns.0.runs', 1)
                ->where('turns.0.runs.0.id', $failed->id)
                ->where('turns.0.runs.0.status', 'failed')
                ->where('turns.1.number', 1)
                ->has('turns.1.runs', 1)
                ->where('turns.1.runs.0.status', 'succeeded')
                ->where('turns.1.runs.0.facility.name', 'Test Yard')
                ->where('turns.1.runs.0.facility.corporation.name', 'Test History Combine')
                ->where('turns.1.runs.0.cards_passed', 3)
                ->where('turns.1.runs.0.runners.0.name', 'Wicker')
                ->where('turns.1.runs.0.runners.0.is_leader', true)
                ->where('turns.1.runs.0.runners.0.gang.name', 'Test History Crew')
                ->where('turns.1.runs.0.runners.1.name', 'Ghost')
                ->where('turns.1.runs.0.runners.1.left', true)
                // The whole log, not the last few lines the run screen opens on.
                ->has('turns.1.runs.0.log', 6));
    }

    public function test_a_player_cannot_open_the_history(): void
    {
        $user = User::factory()->create();
        Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
        ]);

        $this->actingAs($user)
            ->get("/control/games/{$this->game->id}/runs")
            ->assertForbidden();
    }

    private function control(): User
    {
        $user = User::factory()->create();
        ControlMember::factory()->for($this->game)->create(['user_id' => $user->id]);

        return $user;
    }
}
