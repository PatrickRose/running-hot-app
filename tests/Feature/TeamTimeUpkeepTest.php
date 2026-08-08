<?php

namespace Tests\Feature;

use App\Actions\ApplyTeamTimeUpkeep;
use App\Enums\CharacterRole;
use App\Enums\PhaseType;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Phase;
use App\Services\TurnEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TeamTimeUpkeepTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_is_paid_into_credits_when_team_time_opens(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create([
            'income' => 12,
            'credits' => 20,
        ]);

        $engine = app(TurnEngine::class);
        $phase = $engine->advance($engine->advance($engine->start($game)));

        $this->assertSame(PhaseType::TeamTime, $phase->type);
        $this->assertSame(32, $corporation->fresh()->credits);
    }

    public function test_runners_heal_a_single_wound_for_free(): void
    {
        $game = Game::factory()->create();
        $runner = Character::factory()->for($game)->create([
            'role' => CharacterRole::Runner,
            'wounds' => 3,
        ]);

        $this->runUpkeep($game);

        $this->assertSame(2, $runner->fresh()->wounds, 'Exactly one Wound heals per Team Time.');
    }

    public function test_freelancers_also_heal_because_they_take_part_in_runs(): void
    {
        $game = Game::factory()->create();
        $freelancer = Character::factory()->for($game)->create([
            'role' => CharacterRole::Freelancer,
            'wounds' => 2,
        ]);

        $this->runUpkeep($game);

        $this->assertSame(1, $freelancer->fresh()->wounds);
    }

    public function test_corporate_roles_do_not_heal(): void
    {
        $game = Game::factory()->create();
        $ceo = Character::factory()->for($game)->create([
            'role' => CharacterRole::Ceo,
            'wounds' => 2,
        ]);

        $this->runUpkeep($game);

        $this->assertSame(2, $ceo->fresh()->wounds);
    }

    public function test_wounds_never_go_negative(): void
    {
        $game = Game::factory()->create();
        $runner = Character::factory()->for($game)->create([
            'role' => CharacterRole::Runner,
            'wounds' => 0,
        ]);

        $this->runUpkeep($game);

        $this->assertSame(0, $runner->fresh()->wounds);
    }

    public function test_tags_are_not_removed_automatically(): void
    {
        $game = Game::factory()->create();
        $runner = Character::factory()->for($game)->create([
            'role' => CharacterRole::Runner,
            'tags' => 2,
            'credits' => 50,
        ]);

        $this->runUpkeep($game);

        $this->assertSame(2, $runner->fresh()->tags, 'Buying off a Tag is the player\'s choice.');
    }

    public function test_upkeep_is_not_applied_twice_for_the_same_phase(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create([
            'income' => 10,
            'credits' => 0,
        ]);

        $phase = $this->runUpkeep($game);

        $result = app(ApplyTeamTimeUpkeep::class)->handle($phase->fresh());

        $this->assertTrue($result['skipped']);
        $this->assertSame(10, $corporation->fresh()->credits);
    }

    public function test_upkeep_refuses_to_run_outside_team_time(): void
    {
        $game = Game::factory()->create();
        $setup = app(TurnEngine::class)->start($game);

        $this->expectException(InvalidArgumentException::class);

        app(ApplyTeamTimeUpkeep::class)->handle($setup);
    }

    public function test_every_upkeep_movement_is_recorded_in_the_ledger(): void
    {
        $game = Game::factory()->create();
        Corporation::factory()->for($game)->create(['income' => 5, 'credits' => 0]);
        Character::factory()->for($game)->create([
            'role' => CharacterRole::Runner,
            'wounds' => 1,
        ]);

        $this->runUpkeep($game);

        $this->assertSame(2, $game->trackerAdjustments()->count());
        $this->assertSame(2, $game->trackerAdjustments()->where('automated', true)->count());
    }

    protected function runUpkeep(Game $game): Phase
    {
        $engine = app(TurnEngine::class);

        return $engine->advance($engine->advance($engine->start($game)));
    }
}
