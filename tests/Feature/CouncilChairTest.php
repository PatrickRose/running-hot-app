<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\User;
use App\Services\CouncilService;
use App\Services\TurnEngine;
use App\Support\CouncilPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may take the Chair (rulebook 3.1.1).
 *
 * The rotation is "an order announced by Council Control on the day" rather
 * than a rule about who may be in it, so it is the designer's ruling that a
 * seat Control has given somebody may chair exactly as a Corporation does -
 * which is what the game needs on its first turn, because it opens with HM
 * Government chairing.
 *
 * Two asymmetries are the whole of the design and are what these hold onto. A
 * Corporation is in the rotation by being a Corporation; a seat is in it only
 * when Control has put it there. And a CEO never chairs as themselves - they
 * chair for their Corporation, which is what goes in the Chair.
 */
class CouncilChairTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $gordon;

    private Corporation $dtc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);

        $this->gordon = Corporation::factory()->for($this->game)->create([
            'name' => 'Gordon',
            'political_will' => 8,
            'council_chair_order' => 2,
        ]);

        $this->dtc = Corporation::factory()->for($this->game)->create([
            'name' => 'DTC',
            'political_will' => 8,
            'council_chair_order' => 3,
        ]);
    }

    public function test_control_can_give_a_seat_the_chair(): void
    {
        $government = $this->government();

        app(TurnEngine::class)->start($this->game);

        $this->actingAs($this->control())
            ->post(route('control.council.chair', ['game' => $this->game->id]), [
                'chair_type' => 'character',
                'chair_id' => $government->id,
            ])
            ->assertSessionHasNoErrors();

        $session = $this->game->refresh()->currentTurn()->councilSession()->first();

        $this->assertTrue($session->isChairedBy($government));
        $this->assertTrue($government->is($session->chair));
    }

    /**
     * And the powers follow the seat, which is the point of moving it: the
     * player holding the Government reads as the Chair and may act as one.
     */
    public function test_the_player_holding_that_seat_chairs(): void
    {
        $user = User::factory()->create();
        $government = $this->government($user);

        app(TurnEngine::class)->start($this->game);
        $session = $this->game->refresh()->currentTurn()->councilSession()->first();

        app(CouncilService::class)->setChair($session, $government);

        $viewer = app(CouncilPresenter::class)->forPlayer($this->game, $user)['viewer'];

        $this->assertTrue($viewer['is_chair']);
        $this->assertTrue($viewer['can_chair']);

        // And the CEO whose Corporation the rotation would otherwise have put
        // there is not the Chair any more.
        $ceo = $this->ceo($this->gordon);
        $rival = app(CouncilPresenter::class)->forPlayer($this->game, $ceo)['viewer'];

        $this->assertFalse($rival['is_chair']);
        $this->assertFalse($rival['can_chair']);
    }

    /**
     * A seat Control has taken away is not a Chair, whatever the sitting still
     * has written on it.
     */
    public function test_a_seat_that_has_been_taken_away_no_longer_chairs(): void
    {
        $user = User::factory()->create();
        $government = $this->government($user);

        app(TurnEngine::class)->start($this->game);
        $session = $this->game->refresh()->currentTurn()->councilSession()->first();

        app(CouncilService::class)->setChair($session, $government);
        app(CouncilService::class)->seat($government, null);

        $viewer = app(CouncilPresenter::class)->forPlayer($this->game, $user)['viewer'];

        $this->assertFalse($viewer['is_chair']);
        $this->assertFalse($viewer['can_chair']);
    }

    /**
     * A CEO chairs for their Corporation, so naming one here is refused rather
     * than stored as a seat that does nothing - the same answer seat() gives a
     * CEO asking for a bloc of their own.
     */
    public function test_a_ceo_cannot_take_the_chair_as_themselves(): void
    {
        $this->ceo($this->gordon);

        /** @var Character $ceo */
        $ceo = $this->game->characters()->where('role', CharacterRole::Ceo)->firstOrFail();

        app(TurnEngine::class)->start($this->game);

        $this->actingAs($this->control())
            ->post(route('control.council.chair', ['game' => $this->game->id]), [
                'chair_type' => 'character',
                'chair_id' => $ceo->id,
            ])
            ->assertSessionHasErrors('chair');
    }

    public function test_somebody_with_no_seat_cannot_take_the_chair(): void
    {
        $runner = Character::factory()->for($this->game)->create([
            'name' => 'Wicker',
            'role' => CharacterRole::Runner,
        ]);

        app(TurnEngine::class)->start($this->game);

        $this->actingAs($this->control())
            ->post(route('control.council.chair', ['game' => $this->game->id]), [
                'chair_type' => 'character',
                'chair_id' => $runner->id,
            ])
            ->assertSessionHasErrors('chair');
    }

    /**
     * The asymmetry: a Corporation is in the rotation by being one, and a seat
     * is in it only when Control has ordered it.
     */
    public function test_a_seat_is_in_the_rotation_only_when_control_orders_it(): void
    {
        $government = $this->government();

        $council = app(CouncilService::class);

        $this->assertSame(
            ['Gordon', 'DTC'],
            $council->rotation($this->game)->pluck('name')->all(),
        );

        $government->forceFill(['council_chair_order' => 1])->save();

        $this->assertSame(
            ['HM Government', 'Gordon', 'DTC'],
            $council->rotation($this->game)->pluck('name')->all(),
        );
    }

    /**
     * Which is what makes the first turn of the game the Government's, since
     * the rotation steps through by turn number.
     */
    public function test_the_first_turn_goes_to_whoever_is_first_in_the_rotation(): void
    {
        $government = $this->government();
        $government->forceFill(['council_chair_order' => 1])->save();

        app(TurnEngine::class)->start($this->game);

        $session = $this->game->refresh()->currentTurn()->councilSession()->first();

        $this->assertTrue($session->isChairedBy($government));
    }

    /**
     * Saving the rotation is saving the whole list, so a seat left out of it
     * leaves the rotation. A Corporation cannot: it is in either way.
     */
    public function test_a_seat_left_out_of_the_saved_rotation_leaves_it(): void
    {
        $government = $this->government();
        $government->forceFill(['council_chair_order' => 1])->save();

        $this->actingAs($this->control())
            ->post(route('control.council.rotation', ['game' => $this->game->id]), [
                'order' => [
                    ['type' => 'corporation', 'id' => $this->dtc->id],
                    ['type' => 'corporation', 'id' => $this->gordon->id],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($government->refresh()->council_chair_order);
        $this->assertSame(
            ['DTC', 'Gordon'],
            app(CouncilService::class)->rotation($this->game)->pluck('name')->all(),
        );
    }

    /**
     * Losing the seat loses the place in the rotation with it. Left behind,
     * the number would put somebody silently back in the rotation the moment
     * Control seated them again - which is the one thing the rotation, an
     * order Control announces on the day, is not allowed to do for itself.
     */
    public function test_taking_a_seat_away_takes_it_out_of_the_rotation(): void
    {
        $government = $this->government();
        $government->forceFill(['council_chair_order' => 1])->save();

        $council = app(CouncilService::class);

        $council->seat($government, null);

        $this->assertNull($government->refresh()->council_chair_order);
        $this->assertSame(
            ['Gordon', 'DTC'],
            $council->rotation($this->game)->pluck('name')->all(),
        );

        // And seating them again does not put them back in it.
        $council->seat($government, 6);

        $this->assertSame(
            ['Gordon', 'DTC'],
            $council->rotation($this->game)->pluck('name')->all(),
        );
    }

    /**
     * Changing what a seat is worth is not leaving it, so the order stays.
     */
    public function test_changing_a_seats_votes_leaves_its_place_alone(): void
    {
        $government = $this->government();
        $government->forceFill(['council_chair_order' => 1])->save();

        app(CouncilService::class)->seat($government, 4);

        $this->assertSame(1, $government->refresh()->council_chair_order);
    }

    /**
     * A character with no seat would be written into the rotation and filtered
     * straight back out of it, which is a silent no-op rather than an answer.
     */
    public function test_somebody_with_no_seat_cannot_be_put_in_the_rotation(): void
    {
        $runner = Character::factory()->for($this->game)->create([
            'name' => 'Wicker',
            'role' => CharacterRole::Runner,
        ]);

        $this->actingAs($this->control())
            ->post(route('control.council.rotation', ['game' => $this->game->id]), [
                'order' => [
                    ['type' => 'character', 'id' => $runner->id],
                    ['type' => 'corporation', 'id' => $this->gordon->id],
                ],
            ])
            ->assertSessionHasErrors('order');

        // And nothing was written on the way to the refusal.
        $this->assertNull($runner->refresh()->council_chair_order);
        $this->assertSame(2, $this->gordon->refresh()->council_chair_order);
    }

    public function test_the_rotation_takes_both_kinds_in_the_order_given(): void
    {
        $government = $this->government();

        $this->actingAs($this->control())
            ->post(route('control.council.rotation', ['game' => $this->game->id]), [
                'order' => [
                    ['type' => 'character', 'id' => $government->id],
                    ['type' => 'corporation', 'id' => $this->gordon->id],
                    ['type' => 'corporation', 'id' => $this->dtc->id],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['HM Government', 'Gordon', 'DTC'],
            app(CouncilService::class)->rotation($this->game)->pluck('name')->all(),
        );
    }

    /**
     * The Control panel has to be able to offer the Chair to a seat, so the
     * seat carries whether it is in the Chair and where it sits in the
     * rotation.
     */
    public function test_the_control_panel_says_which_seat_is_chairing(): void
    {
        $government = $this->government();
        $government->forceFill(['council_chair_order' => 1])->save();

        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

        $control = app(CouncilPresenter::class)->forControl($this->game);

        $this->assertSame(
            ['HM Government', 'Gordon', 'DTC'],
            array_column($control['rotation'], 'name'),
        );
        $this->assertSame(
            ['character:'.$government->id, 'corporation:'.$this->gordon->id, 'corporation:'.$this->dtc->id],
            array_column($control['rotation'], 'key'),
        );
        $this->assertTrue($control['rotation'][0]['is_chair']);

        $this->assertSame(['HM Government'], array_column($control['own_seats'], 'name'));
        $this->assertTrue($control['own_seats'][0]['is_chair']);
        $this->assertSame(1, $control['own_seats'][0]['chair_order']);
    }

    /**
     * And it says who is chairing before the Council has sat, which is most of
     * the time Control is looking at the panel: the sitting is made when Setup
     * opens, so until then there is no chair to mark and the page could only
     * say nothing. Whose turn it is by the rotation is the honest answer, and
     * the server works it out rather than the browser stepping through the
     * order by turn number.
     */
    public function test_the_panel_names_the_next_chair_before_the_council_sits(): void
    {
        $government = $this->government();
        $government->forceFill(['council_chair_order' => 1])->save();

        $control = app(CouncilPresenter::class)->forControl($this->game);

        $this->assertSame('HM Government', $control['next_chair']['name']);
        $this->assertSame('character:'.$government->id, $control['next_chair']['key']);

        // Nothing is chairing yet, so the panel has nothing else to go on.
        $this->assertSame(
            [false, false, false],
            array_column($control['rotation'], 'is_chair'),
        );
    }

    /**
     * A game with nobody in the rotation at all has nobody to name, which is a
     * roster Control has not built yet rather than an error.
     */
    public function test_the_next_chair_is_nobody_where_the_rotation_is_empty(): void
    {
        $empty = Game::factory()->create();

        $this->assertNull(app(CouncilPresenter::class)->forControl($empty)['next_chair']);
    }

    private function government(?User $user = null): Character
    {
        return Character::factory()->for($this->game)->create([
            'user_id' => $user?->id,
            'name' => 'HM Government',
            'role' => CharacterRole::Other,
            'council_votes' => 6,
        ]);
    }

    private function ceo(Corporation $corporation): User
    {
        $user = User::factory()->create();

        Character::factory()->for($this->game)->create([
            'user_id' => $user->id,
            'corporation_id' => $corporation->id,
            'role' => CharacterRole::Ceo,
        ]);

        return $user;
    }

    private function control(): User
    {
        $user = User::factory()->create();

        ControlMember::factory()->for($this->game)->create(['user_id' => $user->id]);

        return $user;
    }
}
