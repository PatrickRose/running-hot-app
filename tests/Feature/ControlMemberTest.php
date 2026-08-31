<?php

namespace Tests\Feature;

use App\Actions\ClaimControlSeatsForUser;
use App\Models\ControlMember;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Naming a game's Control team by Discord handle, and what a seat is worth.
 */
class ControlMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_names_a_member_by_discord_handle(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$game->id}/control-members", [
                'discord_username' => '@Patrick_Rose',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('control_members', [
            'game_id' => $game->id,
            // Stored in the form claims are matched on, not as it was typed.
            'discord_username' => 'patrick_rose',
            'user_id' => null,
        ]);
    }

    public function test_a_handle_already_on_the_team_is_refused(): void
    {
        $game = Game::factory()->create();
        ControlMember::factory()->for($game)->create(['discord_username' => 'patrick_rose']);

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$game->id}/control-members", [
                'discord_username' => 'Patrick_Rose',
            ])
            ->assertSessionHasErrors('discord_username');

        $this->assertSame(1, ControlMember::query()->count());
    }

    public function test_the_same_handle_may_be_control_of_two_games(): void
    {
        $control = User::factory()->control()->create();
        $first = Game::factory()->create();
        $second = Game::factory()->create();

        foreach ([$first, $second] as $game) {
            $this->actingAs($control)
                ->post("/control/games/{$game->id}/control-members", [
                    'discord_username' => 'patrick_rose',
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, ControlMember::query()->count());
    }

    public function test_a_seat_is_claimed_by_the_handle_signing_in(): void
    {
        $game = Game::factory()->create();
        $seat = ControlMember::factory()->for($game)->create(['discord_username' => 'patrick_rose']);
        $user = User::factory()->create(['discord_username' => 'Patrick_Rose']);

        $claimed = app(ClaimControlSeatsForUser::class)->handle($user);

        $this->assertCount(1, $claimed);
        $this->assertSame($user->id, $seat->fresh()->user_id);
    }

    public function test_a_second_seat_in_the_same_game_is_not_claimed(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create(['discord_username' => 'patrick_rose']);
        ControlMember::factory()->for($game)->create([
            'discord_username' => 'patrick_rose',
            'user_id' => $user->id,
        ]);
        // The same person named a second time, before the first seat was bound.
        $duplicate = ControlMember::factory()->for($game)->create(['discord_username' => 'patrick_rose']);

        $claimed = app(ClaimControlSeatsForUser::class)->handle($user);

        $this->assertSame([], $claimed);
        $this->assertNull($duplicate->fresh()->user_id);
    }

    public function test_a_claimed_seat_survives_a_discord_rename(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create(['discord_username' => 'patrick_rose']);
        ControlMember::factory()->for($game)->create([
            'discord_username' => 'patrick_rose',
            'user_id' => $user->id,
        ]);

        $user->forceFill(['discord_username' => 'someone_else_entirely'])->save();

        $this->assertTrue($user->fresh()->isControlFor($game));
    }

    public function test_a_member_may_run_the_game_they_are_named_on(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();
        ControlMember::factory()->for($game)->create(['user_id' => $user->id]);

        $this->actingAs($user)->get("/control/games/{$game->id}")->assertOk();
        $this->actingAs($user)->post("/control/games/{$game->id}/phase/start")->assertRedirect();
    }

    public function test_a_member_may_not_run_another_game(): void
    {
        $theirs = Game::factory()->create();
        $someone_elses = Game::factory()->create();
        $user = User::factory()->create();
        ControlMember::factory()->for($theirs)->create(['user_id' => $user->id]);

        $this->actingAs($user)->get("/control/games/{$someone_elses->id}")->assertForbidden();
        $this->actingAs($user)
            ->post("/control/games/{$someone_elses->id}/phase/start")
            ->assertForbidden();
    }

    public function test_the_game_list_shows_a_member_only_their_own_games(): void
    {
        $theirs = Game::factory()->create(['name' => 'Saturday']);
        Game::factory()->create(['name' => 'Somebody Else\'s']);
        $user = User::factory()->create();
        ControlMember::factory()->for($theirs)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/control/games')
            ->assertInertia(fn ($page) => $page
                ->has('games', 1)
                ->where('games.0.name', 'Saturday'));
    }

    public function test_control_of_everything_still_sees_every_game(): void
    {
        Game::factory()->count(2)->create();

        $this->actingAs(User::factory()->control()->create())
            ->get('/control/games')
            ->assertInertia(fn ($page) => $page->has('games', 2));
    }

    public function test_a_member_creating_a_game_is_control_of_it(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create(['discord_username' => 'patrick_rose']);
        ControlMember::factory()->for($game)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/control/games', ['name' => 'Second Saturday'])
            ->assertRedirect();

        $created = Game::query()->where('name', 'Second Saturday')->firstOrFail();

        $this->assertTrue($user->fresh()->isControlFor($created));
    }

    public function test_removing_a_seat_takes_the_panel_away(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();
        $seat = ControlMember::factory()->for($game)->create(['user_id' => $user->id]);

        $this->actingAs(User::factory()->control()->create())
            ->delete("/control/games/{$game->id}/control-members/{$seat->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('control_members', ['id' => $seat->id]);
        $this->actingAs($user)->get("/control/games/{$game->id}")->assertForbidden();
    }

    public function test_a_seat_may_not_be_removed_through_another_game(): void
    {
        $game = Game::factory()->create();
        $other = Game::factory()->create();
        $seat = ControlMember::factory()->for($game)->create();

        $this->actingAs(User::factory()->control()->create())
            ->delete("/control/games/{$other->id}/control-members/{$seat->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('control_members', ['id' => $seat->id]);
    }

    public function test_a_player_is_still_kept_out_of_the_control_area(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/control/games/{$game->id}")
            ->assertForbidden();
    }

    public function test_naming_someone_who_has_already_signed_in_binds_them_at_once(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create(['discord_username' => 'patrick_rose']);

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$game->id}/control-members", [
                'discord_username' => 'patrick_rose',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('control_members', [
            'game_id' => $game->id,
            'user_id' => $user->id,
        ]);
        $this->actingAs($user)->get("/control/games/{$game->id}")->assertOk();
    }
}
