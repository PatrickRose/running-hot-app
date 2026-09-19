<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * What the header strip shows, beside the clock, on every page.
 *
 * The clock is shared from HandleInertiaRequests because the whole game runs to
 * it; these numbers are there for the same reason. Every decision the game asks
 * of a player is "can I afford this?", and it was only answerable by leaving
 * whatever page the decision was on.
 */
class PlayerStandingTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->running()->create([
            'stability' => 4,
            'civil_unrest' => 2,
        ]);
    }

    private function player(Character $character): User
    {
        $user = User::factory()->create();
        $character->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    public function test_a_runner_sees_their_own_credits_wounds_and_tags(): void
    {
        $runner = Character::factory()->for($this->game)->runner()->create([
            'name' => 'Jack Scanton',
            'credits' => 12,
            'wounds' => 1,
            'tags' => 2,
            'body' => 3,
        ]);

        $this->actingAs($this->player($runner))
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('standing.characters', 1)
                ->where('standing.characters.0.subject', 'Jack Scanton')
                ->where('standing.characters.0.credits', 12)
                ->where('standing.characters.0.wounds', 1)
                ->where('standing.characters.0.tags', 2)
                ->where('standing.characters.0.incapacitated', false));
    }

    /**
     * A Corporate player spends their Corporation's Credits and has no purse of
     * their own, so that is the number they are shown - and Wounds and Tags are
     * null rather than zero, because a CEO does not have none of them, they do
     * not have any.
     */
    public function test_a_corporate_player_sees_their_corporations_credits(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create([
            'name' => 'Augmented Nucleotech',
            'credits' => 31,
        ]);

        $ceo = Character::factory()
            ->for($this->game)
            ->corporate(CharacterRole::Ceo, $corporation)
            ->create(['name' => 'Ada Bright', 'credits' => 0]);

        $this->actingAs($this->player($ceo))
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('standing.characters', 1)
                ->where('standing.characters.0.subject', 'Augmented Nucleotech')
                ->where('standing.characters.0.credits', 31)
                ->where('standing.characters.0.wounds', null)
                ->where('standing.characters.0.tags', null));
    }

    /**
     * The characters that are organisations rather than people never walk into
     * a Facility, so nothing in the rulebook gives them a Wound, a Tag or a
     * purse. They are left out entirely rather than sent as a row of zeroes.
     */
    public function test_the_press_and_hm_government_carry_no_numbers_of_their_own(): void
    {
        foreach ([CharacterRole::Press, CharacterRole::Other] as $role) {
            $character = Character::factory()->for($this->game)->create([
                'name' => 'HM Government '.$role->value,
                'role' => $role,
                'credits' => 99,
            ]);

            $this->actingAs($this->player($character))
                ->get(route('dashboard'))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->has('standing.characters', 0));
        }
    }

    /**
     * Procatorion belongs to the game rather than to anybody, so everyone is
     * shown it - including the people who carry no numbers of their own, and
     * Control, who holds no character at all.
     */
    public function test_everybody_sees_stability_and_civil_unrest(): void
    {
        $this->actingAs(User::factory()->control()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('standing.stability', 4)
                ->where('standing.civil_unrest', 2)
                ->has('standing.characters', 0));
    }

    /**
     * The reason it is an always prop rather than an ordinary shared one. A
     * partial reload does not carry a plain shared prop, so a page polling
     * `only: ['research']` would keep drawing the Credits it loaded with - and
     * a stale number in a header reads exactly like a current one.
     */
    public function test_the_standing_rides_a_partial_reload(): void
    {
        $runner = Character::factory()->for($this->game)->runner()->create([
            'credits' => 5,
        ]);

        $this->actingAs($this->player($runner))
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('standing.characters.0.credits', 5)
                // Naming only `game` is what every polling page does, and an
                // ordinary shared prop would be filtered straight out of it.
                ->reloadOnly('game', fn (AssertableInertia $reload) => $reload
                    ->where('standing.characters.0.credits', 5)
                    ->where('standing.stability', 4)));
    }

    public function test_a_game_that_is_not_running_carries_no_standing(): void
    {
        $this->game->forceFill(['status' => 'draft'])->save();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('standing', null));
    }
}
