<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\User;
use App\Services\TurnEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * One player paying another. A Runner or Freelancer pays out of their own
 * Credits, a CEO out of their Corporation's, and both halves are ledgered.
 */
class CreditTransferTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);

        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();
    }

    public function test_a_runner_pays_another_runner_and_both_sides_are_ledgered(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner(['name' => 'Wicker', 'credits' => 10, 'user_id' => $user->id]);
        $ghost = $this->runner(['name' => 'Ghost', 'credits' => 2]);

        $this->actingAs($user)
            ->post('/credits/give', $this->payload($wicker, 'character', $ghost->id, 4))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $wicker->refresh()->credits);
        $this->assertSame(6, $ghost->refresh()->credits);

        $adjustments = $this->game->trackerAdjustments()->where('tracker', Tracker::CharacterCredits)->get();

        $this->assertCount(2, $adjustments);
        $this->assertSame([-4, 4], $adjustments->pluck('delta')->all());
        $this->assertTrue($adjustments->every(fn ($row): bool => $row->actor_id === $user->id));
    }

    public function test_a_ceo_pays_out_of_their_corporation(): void
    {
        $user = User::factory()->create();
        $corporation = Corporation::factory()->for($this->game)->create(['credits' => 20]);
        $ceo = Character::factory()->for($this->game)->corporate(CharacterRole::Ceo, $corporation)
            ->create(['user_id' => $user->id]);
        $ghost = $this->runner(['credits' => 0]);

        $this->actingAs($user)
            ->post('/credits/give', $this->payload($ceo, 'character', $ghost->id, 5))
            ->assertSessionHasNoErrors();

        $this->assertSame(15, $corporation->refresh()->credits);
        $this->assertSame(5, $ghost->refresh()->credits);
    }

    public function test_a_runner_pays_a_corporation(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner(['credits' => 8, 'user_id' => $user->id]);
        $corporation = Corporation::factory()->for($this->game)->create(['credits' => 1]);

        $this->actingAs($user)
            ->post('/credits/give', $this->payload($wicker, 'corporation', $corporation->id, 3))
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $wicker->refresh()->credits);
        $this->assertSame(4, $corporation->refresh()->credits);
    }

    /**
     * A purse two people can spend out of is a purse neither can plan with,
     * so only the CEO hands the Corporation's Credits out.
     */
    public function test_security_cannot_give_the_corporations_credits(): void
    {
        $user = User::factory()->create();
        $corporation = Corporation::factory()->for($this->game)->create(['credits' => 20]);
        $security = Character::factory()->for($this->game)->corporate(CharacterRole::Security, $corporation)
            ->create(['user_id' => $user->id]);
        $ghost = $this->runner();

        $this->actingAs($user)
            ->post('/credits/give', $this->payload($security, 'character', $ghost->id, 5))
            ->assertSessionHasErrors('from_character_id');

        $this->assertSame(20, $corporation->refresh()->credits);
    }

    /**
     * A Corporate seat spends its Corporation's Credits, so Credits paid to
     * the CEO personally would sit in a purse nothing reads.
     */
    public function test_a_corporate_seat_cannot_be_paid_personally(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner(['credits' => 8, 'user_id' => $user->id]);
        $ceo = Character::factory()->for($this->game)->corporate(CharacterRole::Ceo)->create();

        $this->actingAs($user)
            ->post('/credits/give', $this->payload($wicker, 'character', $ceo->id, 3))
            ->assertSessionHasErrors('to_id');

        $this->assertSame(8, $wicker->refresh()->credits);
    }

    public function test_nobody_gives_more_than_they_have(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner(['credits' => 3, 'user_id' => $user->id]);
        $ghost = $this->runner(['credits' => 0]);

        $this->actingAs($user)
            ->post('/credits/give', $this->payload($wicker, 'character', $ghost->id, 4))
            ->assertSessionHasErrors('amount');

        $this->assertSame(3, $wicker->refresh()->credits);
        $this->assertSame(0, $ghost->refresh()->credits);
        $this->assertSame(0, $this->game->trackerAdjustments()->count());
    }

    public function test_nobody_pays_out_of_a_seat_that_is_not_theirs(): void
    {
        $wicker = $this->runner(['credits' => 10]);
        $ghost = $this->runner();

        // In the game on a seat of their own, so the refusal is about *this* seat.
        $stranger = User::factory()->create();
        $this->runner(['user_id' => $stranger->id]);

        $this->actingAs($stranger)
            ->post('/credits/give', $this->payload($wicker, 'character', $ghost->id, 1))
            ->assertForbidden();

        $this->assertSame(10, $wicker->refresh()->credits);
    }

    public function test_nothing_is_given_before_the_game_is_running(): void
    {
        $this->game->update(['status' => GameStatus::Draft]);

        $user = User::factory()->create();
        $wicker = $this->runner(['credits' => 10, 'user_id' => $user->id]);
        $ghost = $this->runner();

        $this->actingAs($user)
            ->post('/credits/give', $this->payload($wicker, 'character', $ghost->id, 1))
            ->assertForbidden();
    }

    public function test_the_dashboard_offers_the_purse_and_who_can_be_paid(): void
    {
        $user = User::factory()->create();
        $corporation = Corporation::factory()->for($this->game)->create(['credits' => 20]);
        Character::factory()->for($this->game)->corporate(CharacterRole::Ceo, $corporation)
            ->create(['user_id' => $user->id]);
        Character::factory()->for($this->game)->corporate(CharacterRole::Security, $corporation)->create();
        $ghost = $this->runner(['name' => 'Ghost']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('characters.0.purse', ['name' => $corporation->name, 'credits' => 20])
                ->has('creditRecipients', 2)
                ->where('creditRecipients.0.type', 'corporation')
                ->where('creditRecipients.1.id', $ghost->id));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function runner(array $attributes = []): Character
    {
        return Character::factory()->for($this->game)->runner()->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Character $from, string $toType, int $toId, int $amount): array
    {
        return [
            'from_character_id' => $from->id,
            'to_type' => $toType,
            'to_id' => $toId,
            'amount' => $amount,
        ];
    }
}
