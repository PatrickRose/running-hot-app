<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Control taking Credits out of one purse and putting them in another.
 */
class ControlCreditTransferTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private User $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->control = User::factory()->control()->create();
    }

    public function test_control_takes_credits_from_a_corporation_and_gives_them_to_a_runner(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create(['credits' => 20]);
        $ghost = Character::factory()->for($this->game)->runner()->create(['credits' => 1]);

        $this->actingAs($this->control)
            ->post($this->url(), [
                'from_type' => 'corporation',
                'from_id' => $corporation->id,
                'to_type' => 'character',
                'to_id' => $ghost->id,
                'amount' => 7,
                'reason' => 'Blackmail paid out',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(13, $corporation->refresh()->credits);
        $this->assertSame(8, $ghost->refresh()->credits);

        $adjustments = $this->game->trackerAdjustments()->orderBy('id')->get();

        $this->assertSame([Tracker::CorporationCredits, Tracker::CharacterCredits], $adjustments->pluck('tracker')->all());
        $this->assertSame([-7, 7], $adjustments->pluck('delta')->all());
        $this->assertTrue($adjustments->every(fn ($row): bool => $row->actor_id === $this->control->id));
        $this->assertStringContainsString('Blackmail paid out', (string) $adjustments->first()->reason);
    }

    public function test_control_cannot_take_more_than_the_purse_holds(): void
    {
        $wicker = Character::factory()->for($this->game)->runner()->create(['credits' => 3]);
        $corporation = Corporation::factory()->for($this->game)->create(['credits' => 0]);

        $this->actingAs($this->control)
            ->post($this->url(), [
                'from_type' => 'character',
                'from_id' => $wicker->id,
                'to_type' => 'corporation',
                'to_id' => $corporation->id,
                'amount' => 4,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(3, $wicker->refresh()->credits);
        $this->assertSame(0, $corporation->refresh()->credits);
        $this->assertSame(0, $this->game->trackerAdjustments()->count());
    }

    public function test_a_corporate_seat_is_not_a_purse(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create(['credits' => 20]);
        $ceo = Character::factory()->for($this->game)->corporate(CharacterRole::Ceo, $corporation)->create();
        $ghost = Character::factory()->for($this->game)->runner()->create();

        $this->actingAs($this->control)
            ->post($this->url(), [
                'from_type' => 'character',
                'from_id' => $ceo->id,
                'to_type' => 'character',
                'to_id' => $ghost->id,
                'amount' => 1,
            ])
            ->assertSessionHasErrors('from_id');

        $this->assertSame(20, $corporation->refresh()->credits);
    }

    public function test_a_purse_in_another_game_is_refused(): void
    {
        $elsewhere = Corporation::factory()->for(Game::factory())->create(['credits' => 20]);
        $ghost = Character::factory()->for($this->game)->runner()->create();

        $this->actingAs($this->control)
            ->post($this->url(), [
                'from_type' => 'corporation',
                'from_id' => $elsewhere->id,
                'to_type' => 'character',
                'to_id' => $ghost->id,
                'amount' => 1,
            ])
            ->assertSessionHasErrors('from_id');

        $this->assertSame(20, $elsewhere->refresh()->credits);
    }

    public function test_a_player_cannot_move_credits_between_other_people(): void
    {
        $user = User::factory()->create();
        $wicker = Character::factory()->for($this->game)->runner()->create(['credits' => 5, 'user_id' => $user->id]);
        $ghost = Character::factory()->for($this->game)->runner()->create(['credits' => 5]);

        $this->actingAs($user)
            ->post($this->url(), [
                'from_type' => 'character',
                'from_id' => $ghost->id,
                'to_type' => 'character',
                'to_id' => $wicker->id,
                'amount' => 5,
            ])
            ->assertForbidden();

        $this->assertSame(5, $ghost->refresh()->credits);
    }

    private function url(): string
    {
        return "/control/games/{$this->game->id}/credits/move";
    }
}
