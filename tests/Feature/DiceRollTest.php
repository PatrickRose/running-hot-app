<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\DiceRoll;
use App\Models\Game;
use App\Models\User;
use App\Services\Dice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDice;
use Tests\TestCase;

/**
 * A player rolls some d6s and d8s, and Control reads the result.
 *
 * The rule is the game's own - 5 or better is a success on either die - and
 * the rest is about who sees it: the player who rolled, and Control, and
 * nobody else in the game.
 */
class DiceRollTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private FakeDice $dice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);

        $this->dice = new FakeDice;
        $this->app->instance(Dice::class, $this->dice);
    }

    public function test_a_player_rolls_and_five_or_better_is_a_success_on_either_die(): void
    {
        [$user, $runner] = $this->seat('Wicker');

        // Three d6 then two d8, in that order.
        $this->dice->will([5, 4, 6, 8, 3]);

        $this->actingAs($user)
            ->post('/dice', [
                'character_id' => $runner->id,
                'd6' => 3,
                'd8' => 2,
                'purpose' => 'Talking the guard round',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '3 successes. Control can see the roll.');

        /** @var DiceRoll $roll */
        $roll = DiceRoll::query()->sole();

        $this->assertSame(['d6' => [5, 4, 6], 'd8' => [8, 3]], $roll->faces);
        $this->assertSame(3, $roll->successes);
        $this->assertSame($runner->id, $roll->character_id);
        $this->assertSame($user->id, $roll->user_id);
        $this->assertSame('Talking the guard round', $roll->purpose);
        $this->assertSame([[3, 6], [2, 8]], array_map(
            fn (array $call): array => [$call['count'], $call['faces']],
            $this->dice->rolls,
        ));
    }

    public function test_only_one_size_of_die_is_fine(): void
    {
        [$user, $runner] = $this->seat('Wicker');

        $this->dice->will([8, 7]);

        $this->actingAs($user)
            ->post('/dice', ['character_id' => $runner->id, 'd6' => 0, 'd8' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, DiceRoll::query()->sole()->successes);
    }

    public function test_rolling_nothing_is_refused(): void
    {
        [$user, $runner] = $this->seat('Wicker');

        $this->actingAs($user)
            ->post('/dice', ['character_id' => $runner->id, 'd6' => 0, 'd8' => 0])
            ->assertSessionHasErrors('d6');

        $this->assertSame(0, DiceRoll::query()->count());
    }

    public function test_a_pool_past_the_ceiling_is_refused(): void
    {
        [$user, $runner] = $this->seat('Wicker');

        $this->actingAs($user)
            ->post('/dice', ['character_id' => $runner->id, 'd6' => 31, 'd8' => 0])
            ->assertSessionHasErrors('d6');

        $this->assertSame(0, DiceRoll::query()->count());
    }

    public function test_a_player_cannot_roll_as_somebody_elses_seat(): void
    {
        [$user] = $this->seat('Wicker');
        [, $ghost] = $this->seat('Ghost');

        $this->actingAs($user)
            ->post('/dice', ['character_id' => $ghost->id, 'd6' => 1, 'd8' => 0])
            ->assertForbidden();

        $this->assertSame(0, DiceRoll::query()->count());
    }

    public function test_a_player_has_to_say_which_seat_is_rolling(): void
    {
        [$user] = $this->seat('Wicker');

        $this->actingAs($user)
            ->post('/dice', ['d6' => 1, 'd8' => 0])
            ->assertSessionHasErrors('character_id');
    }

    public function test_somebody_holding_no_seat_cannot_roll(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/dice', ['d6' => 1, 'd8' => 0])
            ->assertForbidden();
    }

    /**
     * Reading your rolls is a fact about the roster; rolling is an act, so it
     * asks about the clock.
     */
    public function test_a_game_off_the_clock_refuses_a_roll(): void
    {
        [$user, $runner] = $this->seat('Wicker');
        $this->game->update(['status' => GameStatus::Draft]);

        $this->actingAs($user)
            ->post('/dice', ['character_id' => $runner->id, 'd6' => 1, 'd8' => 0])
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/dice')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_roll', false));
    }

    public function test_control_rolls_without_naming_a_seat(): void
    {
        $user = $this->control();

        $this->dice->will([2]);

        $this->actingAs($user)
            ->post('/dice', ['d6' => 1, 'd8' => 0])
            ->assertSessionHasNoErrors();

        $this->assertNull(DiceRoll::query()->sole()->character_id);
    }

    /**
     * The whole point: a player's result is shared with Control and with no
     * other player.
     */
    public function test_a_player_reads_their_own_rolls_and_nobody_elses(): void
    {
        [$wickerUser, $wicker] = $this->seat('Wicker');
        [$ghostUser, $ghost] = $this->seat('Ghost');

        $this->dice->will([6, 1]);

        $this->actingAs($wickerUser)->post('/dice', ['character_id' => $wicker->id, 'd6' => 1, 'd8' => 0]);
        $this->actingAs($ghostUser)->post('/dice', ['character_id' => $ghost->id, 'd6' => 1, 'd8' => 0]);

        $this->actingAs($wickerUser)
            ->get('/dice')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dice')
                ->has('rolls', 1)
                ->where('rolls.0.character_name', 'Wicker')
                ->where('rolls.0.successes', 1)
                ->where('can_roll', true)
                ->has('seats', 1));
    }

    public function test_control_reads_every_roll_in_the_game(): void
    {
        [$wickerUser, $wicker] = $this->seat('Wicker');
        [$ghostUser, $ghost] = $this->seat('Ghost');

        $this->dice->will([6, 1]);

        $this->actingAs($wickerUser)->post('/dice', ['character_id' => $wicker->id, 'd6' => 1, 'd8' => 0]);
        $this->actingAs($ghostUser)->post('/dice', ['character_id' => $ghost->id, 'd6' => 0, 'd8' => 1]);

        $this->actingAs($this->control())
            ->get("/control/games/{$this->game->id}/dice")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('control/games/dice')
                ->has('rolls', 2)
                // Newest first.
                ->where('rolls.0.character_name', 'Ghost')
                ->where('rolls.0.faces.d8', [1])
                ->where('rolls.0.successes', 0)
                ->where('rolls.1.character_name', 'Wicker')
                ->where('rolls.1.successes', 1));
    }

    public function test_a_player_cannot_open_controls_log(): void
    {
        [$user] = $this->seat('Wicker');

        $this->actingAs($user)
            ->get("/control/games/{$this->game->id}/dice")
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Character}
     */
    private function seat(string $name): array
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
            'name' => $name,
        ]);

        return [$user, $character];
    }

    private function control(): User
    {
        $user = User::factory()->create();
        ControlMember::factory()->for($this->game)->create(['user_id' => $user->id]);

        return $user;
    }
}
