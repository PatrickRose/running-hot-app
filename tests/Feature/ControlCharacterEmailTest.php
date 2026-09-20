<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Control putting the address a player signed up with against their seat.
 *
 * The second claim ticket beside the Discord handle. What is worth pinning is
 * the bound it carries: two characters in one game cannot share an address,
 * because a claim takes every unheld seat on one and would otherwise hand
 * whoever got there first both of them.
 */
class ControlCharacterEmailTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private User $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->control = User::factory()->create();

        ControlMember::factory()->for($this->game)->create(['user_id' => $this->control->id]);
    }

    private function seat(array $attributes = []): Character
    {
        return Character::factory()->create([
            'game_id' => $this->game->id,
            'role' => CharacterRole::Runner,
            ...$attributes,
        ]);
    }

    private function reserve(Character $character, ?string $email): TestResponse
    {
        return $this->actingAs($this->control)->post(
            route('control.characters.email', ['game' => $this->game->id, 'character' => $character->id]),
            ['email' => $email],
        );
    }

    public function test_control_reserves_a_seat_for_an_address(): void
    {
        $character = $this->seat();

        $this->reserve($character, 'Jack@Example.com')->assertRedirect();

        $this->assertSame('jack@example.com', $character->fresh()->email);
    }

    public function test_clearing_it_takes_the_address_off(): void
    {
        $character = $this->seat(['email' => 'jack@example.com']);

        $this->reserve($character, null)->assertRedirect();

        $this->assertNull($character->fresh()->email);
    }

    public function test_two_characters_in_one_game_cannot_share_an_address(): void
    {
        $this->seat(['email' => 'jack@example.com']);
        $other = $this->seat();

        $this->reserve($other, 'jack@example.com')->assertSessionHasErrors('email');

        $this->assertNull($other->fresh()->email);
    }

    /**
     * A different game is a different roster, so the same person may be on
     * both under the same address.
     */
    public function test_the_same_address_may_be_used_in_another_game(): void
    {
        $this->seat(['email' => 'jack@example.com']);

        $other = Game::factory()->create(['status' => GameStatus::Running]);
        ControlMember::factory()->for($other)->create(['user_id' => $this->control->id]);

        $elsewhere = Character::factory()->create([
            'game_id' => $other->id,
            'role' => CharacterRole::Runner,
        ]);

        $this->actingAs($this->control)->post(
            route('control.characters.email', ['game' => $other->id, 'character' => $elsewhere->id]),
            ['email' => 'jack@example.com'],
        )->assertRedirect();

        $this->assertSame('jack@example.com', $elsewhere->fresh()->email);
    }

    /**
     * The courtesy the handle already extends: if that player has signed in
     * already, bind them now rather than sending them round through /claim.
     */
    public function test_a_player_who_has_already_signed_in_is_bound_at_once(): void
    {
        $player = User::factory()->create(['email' => 'jack@example.com']);
        $character = $this->seat();

        $this->reserve($character, 'jack@example.com')->assertRedirect();

        $this->assertSame($player->id, $character->fresh()->user_id);
    }

    public function test_a_player_is_refused(): void
    {
        $player = User::factory()->create();
        $character = $this->seat();

        $this->actingAs($player)->post(
            route('control.characters.email', ['game' => $this->game->id, 'character' => $character->id]),
            ['email' => 'jack@example.com'],
        )->assertForbidden();
    }

    public function test_a_malformed_address_is_refused(): void
    {
        $character = $this->seat();

        $this->reserve($character, 'not-an-address')->assertSessionHasErrors('email');
    }
}
