<?php

namespace Tests\Feature;

use App\Actions\ClaimCharactersForUser;
use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function claim(User $user): array
    {
        return app(ClaimCharactersForUser::class)->handle($user);
    }

    public function test_a_player_is_bound_to_the_character_reserved_for_their_handle(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create([
            'discord_username' => 'nightshift_jax',
            'role' => CharacterRole::Runner,
        ]);
        $user = User::factory()->create(['discord_username' => 'nightshift_jax']);

        $claimed = $this->claim($user);

        $this->assertCount(1, $claimed);
        $this->assertSame($user->id, $character->fresh()->user_id);
    }

    public function test_handles_are_matched_regardless_of_case_or_a_leading_at(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create([
            'discord_username' => '@NightShift_Jax  ',
        ]);

        $this->assertSame('nightshift_jax', $character->fresh()->discord_username);

        $user = User::factory()->create(['discord_username' => 'NIGHTSHIFT_JAX']);

        $this->claim($user);

        $this->assertSame($user->id, $character->fresh()->user_id);
    }

    public function test_a_character_already_claimed_is_not_reassigned(): void
    {
        $game = Game::factory()->create();
        $owner = User::factory()->create(['discord_username' => 'first']);
        $character = Character::factory()->for($game)->create([
            'discord_username' => 'shared_handle',
            'user_id' => $owner->id,
        ]);

        $interloper = User::factory()->create(['discord_username' => 'shared_handle']);

        $claimed = $this->claim($interloper);

        $this->assertSame([], $claimed);
        $this->assertSame($owner->id, $character->fresh()->user_id, 'A claimed character must not be stolen.');
    }

    public function test_a_rename_after_claiming_does_not_detach_the_player(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create(['discord_username' => 'old_handle']);
        $user = User::factory()->create(['discord_username' => 'old_handle']);

        $this->claim($user);
        $this->assertSame($user->id, $character->fresh()->user_id);

        // The player renames themselves on Discord and signs in again.
        $user->forceFill(['discord_username' => 'new_handle'])->save();
        $this->claim($user->fresh());

        $this->assertSame($user->id, $character->fresh()->user_id);
    }

    public function test_a_player_may_hold_several_characters_across_games(): void
    {
        $first = Character::factory()->for(Game::factory()->create())->create([
            'discord_username' => 'multi',
        ]);
        $second = Character::factory()->for(Game::factory()->create())->create([
            'discord_username' => 'multi',
        ]);

        $user = User::factory()->create(['discord_username' => 'multi']);

        $this->assertCount(2, $this->claim($user));
        $this->assertSame($user->id, $first->fresh()->user_id);
        $this->assertSame($user->id, $second->fresh()->user_id);
    }

    public function test_characters_in_a_finished_game_are_not_claimed(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Finished]);
        $character = Character::factory()->for($game)->create(['discord_username' => 'late']);
        $user = User::factory()->create(['discord_username' => 'late']);

        $this->assertSame([], $this->claim($user));
        $this->assertNull($character->fresh()->user_id);
    }

    public function test_a_user_without_a_discord_handle_claims_nothing(): void
    {
        $game = Game::factory()->create();
        Character::factory()->for($game)->create(['discord_username' => 'someone']);
        $user = User::factory()->create(['discord_username' => null]);

        $this->assertSame([], $this->claim($user));
    }

    public function test_control_can_reserve_a_character_for_a_handle(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create();

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$game->id}/characters/{$character->id}/discord", [
                'discord_username' => '@Kestrel_Ade',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('kestrel_ade', $character->fresh()->discord_username);
    }

    public function test_reserving_a_handle_claims_a_player_who_has_already_signed_in(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create();
        $user = User::factory()->create(['discord_username' => 'already_here']);

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$game->id}/characters/{$character->id}/discord", [
                'discord_username' => 'already_here',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($user->id, $character->fresh()->user_id);
    }

    public function test_two_characters_in_one_game_cannot_share_a_handle(): void
    {
        $game = Game::factory()->create();
        Character::factory()->for($game)->create(['discord_username' => 'taken']);
        $other = Character::factory()->for($game)->create();

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$game->id}/characters/{$other->id}/discord", [
                'discord_username' => 'taken',
            ])
            ->assertSessionHasErrors('discord_username');

        $this->assertNull($other->fresh()->discord_username);
    }

    public function test_clearing_the_handle_removes_the_reservation(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create(['discord_username' => 'gone']);

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$game->id}/characters/{$character->id}/discord", [
                'discord_username' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($character->fresh()->discord_username);
    }

    public function test_control_can_release_a_claimed_character(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create(['discord_username' => 'someone']);
        $character = Character::factory()->for($game)->create([
            'discord_username' => 'someone',
            'user_id' => $user->id,
        ]);

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$game->id}/characters/{$character->id}/release")
            ->assertSessionHasNoErrors();

        $this->assertNull($character->fresh()->user_id);
    }

    public function test_a_player_cannot_reserve_characters(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create();

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/characters/{$character->id}/discord", [
                'discord_username' => 'cheeky',
            ])
            ->assertForbidden();

        $this->assertNull($character->fresh()->discord_username);
    }

    public function test_a_character_from_another_game_is_not_reachable(): void
    {
        $game = Game::factory()->create();
        $other = Character::factory()->for(Game::factory()->create())->create();

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$game->id}/characters/{$other->id}/discord", [
                'discord_username' => 'wrong_game',
            ])
            ->assertNotFound();
    }
}
