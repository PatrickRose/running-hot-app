<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use App\Support\EquipmentCardBlueprint;
use App\Support\ProtectionCardBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The card lists every player reads at `/cards`.
 *
 * Protection Cards and Equipment, as printed, and nothing else: no technology,
 * because a tech tree is the Corporation's own, and none of what Control keeps
 * beside a card - how many copies stand across the game's Facilities is
 * reconnaissance, and the notes are Control's.
 */
class PlayerCardListTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
    }

    private function runner(): User
    {
        $user = User::factory()->create();

        Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
        ]);

        return $user;
    }

    public function test_a_runner_reads_every_protection_card_and_every_equipment_card(): void
    {
        $this->actingAs($this->runner())
            ->get(route('cards'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cards')
                ->has('cards.protection', count(ProtectionCardBlueprint::defaults()))
                ->has('cards.equipment', count(EquipmentCardBlueprint::defaults())));
    }

    public function test_the_research_cards_are_not_on_it(): void
    {
        $this->actingAs($this->runner())
            ->get(route('cards'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('technologies')
                ->missing('cards.technologies'));
    }

    /**
     * A catalogue is everybody's; how many copies of a card are standing in
     * the game's Facilities is not, and neither are Control's notes.
     */
    public function test_nothing_control_keeps_beside_a_card_reaches_a_player(): void
    {
        $this->actingAs($this->runner())
            ->get(route('cards'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('cards.protection.0.installed_count')
                ->missing('cards.protection.0.notes')
                ->missing('cards.equipment.0.notes')
                ->has('cards.protection.0.challenge')
                ->has('cards.equipment.0.effect'));
    }

    public function test_somebody_holding_no_seat_may_still_read_it(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('cards'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('cards.protection'));
    }

    public function test_it_reads_the_same_before_the_game_starts(): void
    {
        $this->game->update(['status' => GameStatus::Draft]);

        $this->actingAs($this->runner())
            ->get(route('cards'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('cards.equipment'));
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('cards'))->assertRedirect(route('login'));
    }
}
