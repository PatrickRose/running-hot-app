<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Gang;
use App\Models\TrackerAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Control's one screen for every number in the game (rulebook: Control can
 * override anything).
 *
 * The interesting line here is between the two kinds of number it shows.
 * Wounds, Tags and Credits are Trackers and move through TrackerService, so
 * every change is a ledger row. Brawn, Hack, Charisma and Body are what a
 * character is - nothing in the game spends them - so they are written
 * straight to the row and nothing is logged.
 */
class ControlStatsTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->running()->create();
    }

    private function control(): User
    {
        return User::factory()->control()->create();
    }

    public function test_a_player_cannot_reach_the_stats_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('control.stats.index', $this->game))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('control.stats.index', $this->game))
            ->assertRedirect('/login');
    }

    public function test_control_sees_every_subject_and_every_number(): void
    {
        Corporation::factory()->for($this->game)->create([
            'name' => 'Augmented Nucleotech',
            'credits' => 40,
            'political_will' => 7,
        ]);
        $gang = Gang::factory()->for($this->game)->create(['name' => 'g33ks']);
        // Handed the gang rather than letting the factory make one: a second
        // gang with a random name would sort either side of this one, and the
        // assertions below read the tables in the order the page draws them.
        Character::factory()->for($this->game)->runner($gang)->create([
            'name' => 'Jack Scanton',
            'brawn' => 5,
            'hack' => 2,
            'charisma' => 4,
            'body' => 3,
            'wounds' => 1,
            'notoriety' => 3,
        ]);

        $this->actingAs($this->control())
            ->get(route('control.stats.index', $this->game))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('control/games/stats')
                ->where('trackers.global.values.stability', $this->game->stability)
                ->where('trackers.corporations.0.values.corporation_credits', 40)
                ->where('trackers.corporations.0.values.political_will', 7)
                // The gang carries no tracker of its own: its figure is the
                // total of its members', and the Runner's is the editable one.
                ->where('trackers.gangs.0.notoriety', 3)
                ->where('trackers.gangs.0.members', 1)
                ->where('trackers.characters.0.values.wounds', 1)
                ->where('trackers.characters.0.values.notoriety', 3)
                // The four printed stats, which were editable nowhere at all
                // before this screen.
                ->where('trackers.characters.0.brawn', 5)
                ->where('trackers.characters.0.hack', 2)
                ->where('trackers.characters.0.charisma', 4)
                ->where('trackers.characters.0.body', 3));
    }

    public function test_control_can_edit_a_characters_printed_stats(): void
    {
        $character = Character::factory()->for($this->game)->runner()->create([
            'brawn' => 4, 'hack' => 4, 'charisma' => 3, 'body' => 3,
        ]);

        $this->actingAs($this->control())
            ->patch(route('control.characters.stats', [$this->game, $character]), [
                'brawn' => 6,
                'hack' => 1,
                'charisma' => 5,
                'body' => 4,
            ])
            ->assertRedirect();

        $character->refresh();

        $this->assertSame(6, $character->brawn);
        $this->assertSame(1, $character->hack);
        $this->assertSame(5, $character->charisma);
        $this->assertSame(4, $character->body);
    }

    /**
     * The one write on this screen that does not touch the ledger, and
     * deliberately so: nothing in the rules spends Brawn or Body, so there is
     * no "why did that change?" for a ledger row to answer.
     */
    public function test_editing_the_printed_stats_writes_no_ledger_row(): void
    {
        $character = Character::factory()->for($this->game)->runner()->create();

        $this->actingAs($this->control())
            ->patch(route('control.characters.stats', [$this->game, $character]), [
                'brawn' => 7, 'hack' => 7, 'charisma' => 7, 'body' => 7,
            ]);

        $this->assertSame(0, TrackerAdjustment::query()->count());
    }

    /**
     * A Body of zero would make a character incapacitated before they had taken
     * a Wound (rulebook 3.4.2), which is not a state the game describes.
     */
    public function test_a_body_of_zero_is_refused(): void
    {
        $character = Character::factory()->for($this->game)->runner()->create(['body' => 3]);

        $this->actingAs($this->control())
            ->patch(route('control.characters.stats', [$this->game, $character]), [
                'brawn' => 4, 'hack' => 4, 'charisma' => 3, 'body' => 0,
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame(3, $character->refresh()->body);
    }

    public function test_a_negative_skill_is_refused(): void
    {
        $character = Character::factory()->for($this->game)->runner()->create(['brawn' => 4]);

        $this->actingAs($this->control())
            ->patch(route('control.characters.stats', [$this->game, $character]), [
                'brawn' => -1, 'hack' => 4, 'charisma' => 3, 'body' => 3,
            ])
            ->assertSessionHasErrors('brawn');

        $this->assertSame(4, $character->refresh()->brawn);
    }

    public function test_a_character_from_another_game_is_not_found(): void
    {
        $other = Character::factory()->for(Game::factory()->create())->runner()->create();

        $this->actingAs($this->control())
            ->patch(route('control.characters.stats', [$this->game, $other]), [
                'brawn' => 9, 'hack' => 9, 'charisma' => 9, 'body' => 9,
            ])
            ->assertNotFound();
    }

    /**
     * Trackers still go through TrackerService from this screen, so a Control
     * edit here is as explainable three turns later as it was on the panel.
     */
    public function test_a_tracker_moved_from_this_screen_still_writes_the_ledger(): void
    {
        $character = Character::factory()->for($this->game)->runner()->create(['wounds' => 0]);

        $this->actingAs($this->control())
            ->post(route('control.trackers.store', $this->game), [
                'subject_type' => $character->getMorphClass(),
                'subject_id' => $character->id,
                'tracker' => 'wounds',
                'mode' => 'set',
                'value' => 2,
                'reason' => 'Caught by a Roboscorpion',
            ])
            ->assertRedirect();

        $this->assertSame(2, $character->refresh()->wounds);
        $this->assertDatabaseHas('tracker_adjustments', [
            'tracker' => 'wounds',
            'value_after' => 2,
            'reason' => 'Caught by a Roboscorpion',
        ]);
    }

    /**
     * A Control seat is a seat on one game. Being Control of Saturday's game is
     * not being Control of somebody else's, and the numbers are exactly what a
     * seat on the wrong game must not reach.
     */
    public function test_control_of_another_game_cannot_reach_these_stats(): void
    {
        $other = Game::factory()->create();
        $seated = User::factory()->create();
        $other->controlMembers()->create(['user_id' => $seated->id, 'discord_username' => 'someone']);

        $this->actingAs($seated)
            ->get(route('control.stats.index', $this->game))
            ->assertForbidden();
    }
}
