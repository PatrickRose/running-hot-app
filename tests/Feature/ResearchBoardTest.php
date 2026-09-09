<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\EquationSide;
use App\Enums\GameStatus;
use App\Enums\PhaseType;
use App\Enums\ResearchEquationStatus;
use App\Enums\ResearchSuit;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\TechnologyType;
use App\Models\User;
use App\Services\ResearchTableService;
use App\Services\TrackerService;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

/**
 * The research pages, and the line between what a player may see and what they
 * may do (rulebook 3.2).
 *
 * Two boundaries are being held here. The table itself is public - it is a table
 * in a room, and the turn order is announced - while a hand, a deck and a pile of
 * Research Points belong to one Corporation, because 3.2.5 makes the size of that
 * pile semi-secret. And inside a Corporation, the Research seat plays while the
 * CEO and Security read: a hand two people can play from is a hand neither can
 * plan with.
 */
class ResearchBoardTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $gordon;

    private Corporation $ant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'running_hot.research.private_deck' => ['values' => [1, 2, 3], 'copies' => 1, 'wild' => 0],
            'running_hot.research.public_deck' => ['values' => [1, 2, 3], 'copies' => 1, 'wild' => 0],
        ]);

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

        $this->gordon = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);
        $this->ant = Corporation::factory()->for($this->game)->create(['name' => 'Augmented Nucleotech']);
    }

    private function seat(Corporation $corporation, CharacterRole $role): User
    {
        $user = User::factory()->create();

        $corporation->characters()->create([
            'game_id' => $this->game->id,
            'name' => $corporation->name.' '.$role->label(),
            'role' => $role,
        ])->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function control(): User
    {
        $user = User::factory()->create();

        ControlMember::factory()->for($this->game)->create()
            ->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function table(): ResearchTableService
    {
        return app(ResearchTableService::class);
    }

    public function test_the_research_seat_gets_its_own_hand_and_may_play(): void
    {
        $this->table()->openSession($this->game);

        $response = $this->actingAs($this->seat($this->gordon, CharacterRole::Research))
            ->get(route('research'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableJson $page) => $page
            ->component('research')
            ->where('research.own.name', 'Gordon')
            ->where('research.own.can_play', true)
            ->has('research.own.hand', 5)
            ->has('research.session.pool', 6)
            ->has('research.session.seats', 2)
        );
    }

    public function test_the_ceo_sees_the_same_research_and_cannot_play_it(): void
    {
        $this->table()->openSession($this->game);

        $response = $this->actingAs($this->seat($this->gordon, CharacterRole::Ceo))
            ->get(route('research'));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->component('research')
            ->where('research.own.name', 'Gordon')
            // Not secret from their own Corporation - just not theirs to play.
            ->where('research.own.can_play', false)
            ->has('research.own.hand', 5)
        );
    }

    public function test_a_runner_sees_the_table_and_no_corporations_hand(): void
    {
        $this->table()->openSession($this->game);

        $user = User::factory()->create();

        $this->game->characters()->create([
            'name' => 'Ghost',
            'role' => CharacterRole::Runner,
        ])->forceFill(['user_id' => $user->id])->save();

        $response = $this->actingAs($user)->get(route('research'));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->component('research')
            ->where('research.own', null)
            ->has('research.session.pool', 6)
        );
    }

    public function test_a_rivals_research_player_cannot_play_your_cards(): void
    {
        $session = $this->table()->openSession($this->game);

        $mine = $this->table()->hand($this->gordon)->first();
        $pool = $this->table()->pool($this->game)->first();

        $mine->forceFill(['suit' => ResearchSuit::Leaf, 'value' => 3])->save();
        $pool->forceFill(['suit' => ResearchSuit::Maths, 'value' => 3])->save();

        // ANT's Research player, sending Gordon's card id.
        $response = $this->actingAs($this->seat($this->ant, CharacterRole::Research))
            ->post(route('research.equations.play'), [
                'left' => [$mine->id],
                'right' => [$pool->id],
            ]);

        $response->assertSessionHasErrors('equation');
        $this->assertSame(0, $session->equations()->count());
    }

    public function test_the_research_seat_plays_and_scores_through_the_routes(): void
    {
        $session = $this->table()->openSession($this->game);
        $user = $this->seat($this->gordon, CharacterRole::Research);

        // Gordon has to be the one to play, and the deal is random.
        $this->table()->randomiseOrder($session);
        $session->seats()->where('corporation_id', $this->ant->id)->update(['left_at' => now()]);
        $this->table()->advanceTurn($session->refresh());

        $hand = $this->table()->hand($this->gordon)->first();
        $pool = $this->table()->pool($this->game)->first();

        $hand->forceFill(['suit' => ResearchSuit::Leaf, 'value' => 3])->save();
        $pool->forceFill(['suit' => ResearchSuit::Maths, 'value' => 3])->save();

        $this->actingAs($user)
            ->post(route('research.equations.play'), [
                'left' => [$hand->id],
                'right' => [$pool->id],
            ])
            ->assertSessionHasNoErrors();

        $equation = $this->gordon->researchEquations()->sole();

        $this->assertSame(ResearchEquationStatus::Pending, $equation->status);

        $this->actingAs($user)
            ->post(route('research.equations.score', $equation), [
                'side' => EquationSide::Left->value,
                'suit' => ResearchSuit::Leaf->value,
                'bonus' => [ResearchSuit::Leaf->value => 1],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $this->gordon->refresh()->leaf_points);
    }

    public function test_one_corporation_cannot_score_anothers_equation(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $session->currentSeat()->corporation;

        $hand = $this->table()->hand($corporation)->first();
        $pool = $this->table()->pool($this->game)->first();

        $hand->forceFill(['suit' => ResearchSuit::Leaf, 'value' => 3])->save();
        $pool->forceFill(['suit' => ResearchSuit::Maths, 'value' => 3])->save();

        $equation = $this->table()->play($session, $corporation, [$hand->id], [$pool->id]);

        $other = $corporation->is($this->gordon) ? $this->ant : $this->gordon;

        $this->actingAs($this->seat($other, CharacterRole::Research))
            ->post(route('research.equations.score', $equation), [
                'side' => EquationSide::Left->value,
                'suit' => ResearchSuit::Leaf->value,
                'bonus' => [ResearchSuit::Leaf->value => 1],
            ])
            ->assertForbidden();
    }

    public function test_research_points_are_only_spent_during_setup(): void
    {
        $user = $this->seat($this->gordon, CharacterRole::Research);

        app(TrackerService::class)->set($this->gordon, ResearchSuit::Cog->tracker(), 10);

        /** @var FacilityType $corporate */
        $corporate = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::CORPORATE)->sole();
        $facility = Facility::factory()->for($this->gordon)->for($corporate)->create(['name' => 'Gordon Tower']);

        $technology = TechnologyType::factory()->for($this->game)->create([
            'name' => 'Laser Porridge',
            'cog_cost' => 2,
            'brain_cost' => 0,
            'leaf_cost' => 0,
            'maths_cost' => 0,
        ]);

        $payload = ['technology_type_id' => $technology->id, 'facility_id' => $facility->id];

        // Setup is where 3.2.2 puts it, and the game opens there.
        $this->assertSame(PhaseType::Setup, $this->game->currentPhase()?->type);

        $this->actingAs($user)
            ->post(route('research.technologies.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->gordon->technologyHoldings()->count());

        // In the Action phase the same request is refused.
        app(TurnEngine::class)->advance($this->game->refresh()->currentPhase());

        $this->actingAs($user)
            ->post(route('research.technologies.store'), $payload)
            ->assertSessionHasErrors('technology_type_id');

        $this->assertSame(1, $this->gordon->technologyHoldings()->count());
    }

    public function test_trading_points_is_not_tied_to_a_phase(): void
    {
        app(TrackerService::class)->set($this->gordon, ResearchSuit::Leaf->tracker(), 6);

        app(TurnEngine::class)->advance($this->game->currentPhase());

        $this->actingAs($this->seat($this->gordon, CharacterRole::Research))
            ->post(route('research.points.transfer'), [
                'corporation_id' => $this->ant->id,
                'suit' => ResearchSuit::Leaf->value,
                'amount' => 4,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->gordon->refresh()->leaf_points);
        $this->assertSame(4, $this->ant->refresh()->leaf_points);
    }

    public function test_a_security_player_may_not_play_the_research_game(): void
    {
        $this->table()->openSession($this->game);

        $this->actingAs($this->seat($this->gordon, CharacterRole::Security))
            ->post(route('research.leave'))
            ->assertForbidden();
    }

    public function test_control_reads_every_hand_and_runs_the_table(): void
    {
        $control = $this->control();

        $this->actingAs($control)
            ->get(route('control.research.index', $this->game))
            ->assertOk()
            ->assertInertia(fn (AssertableJson $page) => $page
                ->component('control/games/research')
                ->has('research.corporations', 2)
                ->where('research.session', null)
            );

        $this->actingAs($control)
            ->post(route('control.research.session.store', $this->game))
            ->assertSessionHasNoErrors();

        $this->actingAs($control)
            ->get(route('control.research.index', $this->game))
            ->assertInertia(fn (AssertableJson $page) => $page
                ->has('research.session.seats', 2)
                // Control sees every hand: they are dealing the cards.
                ->has('research.corporations.0.hand', 5)
                ->has('research.corporations.1.hand', 5)
            );
    }

    public function test_control_moves_research_points_with_the_tracker_controls(): void
    {
        // The four suits are Trackers, so Control overrides a grant the same way
        // it overrides Income: from the game panel, into the same ledger.
        $this->actingAs($this->control())
            ->post(route('control.trackers.store', $this->game), [
                'subject_type' => $this->gordon->getMorphClass(),
                'subject_id' => $this->gordon->id,
                'tracker' => ResearchSuit::Brain->tracker()->value,
                'mode' => 'adjust',
                'value' => 9,
                'reason' => 'Ruling at the research table',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(9, $this->gordon->refresh()->brain_points);

        $this->assertDatabaseHas('tracker_adjustments', [
            'subject_id' => $this->gordon->id,
            'tracker' => ResearchSuit::Brain->tracker()->value,
            'delta' => 9,
            'reason' => 'Ruling at the research table',
        ]);
    }

    public function test_a_player_cannot_reach_controls_research_panel(): void
    {
        $this->actingAs($this->seat($this->gordon, CharacterRole::Research))
            ->get(route('control.research.index', $this->game))
            ->assertForbidden();
    }

    public function test_control_hands_the_points_back_from_the_panel(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $session->currentSeat()->corporation;

        $hand = $this->table()->hand($corporation)->first();
        $pool = $this->table()->pool($this->game)->first();

        $hand->forceFill(['suit' => ResearchSuit::Leaf, 'value' => 3])->save();
        $pool->forceFill(['suit' => ResearchSuit::Maths, 'value' => 3])->save();

        $equation = $this->table()->play($session, $corporation, [$hand->id], [$pool->id]);
        $control = $this->control();

        $this->actingAs($control)
            ->post(route('control.research.equations.score', [$this->game, $equation]), [
                'side' => EquationSide::Left->value,
                'suit' => ResearchSuit::Leaf->value,
                'bonus' => [ResearchSuit::Leaf->value => 1],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $corporation->refresh()->leaf_points);

        $this->actingAs($control)
            ->post(route('control.research.equations.unscore', [$this->game, $equation]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $corporation->refresh()->leaf_points);
        $this->assertSame(ResearchEquationStatus::Pending, $equation->refresh()->status);
    }
}
