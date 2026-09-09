<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\ProtectionKind;
use App\Enums\RunnerSkill;
use App\Enums\RunStatus;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\Run;
use App\Models\Turn;
use App\Models\User;
use App\Services\Dice;
use App\Services\RunEngine;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use App\Support\RunPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDice;
use Tests\TestCase;

/**
 * Players driving a run, with Control able to step in (rulebook 3.4).
 *
 * Two things are being held onto here, and the second is the harder one.
 *
 * The boundary: the Run Leader rolls and moves the group on, any Runner may
 * walk away, Security works the cards, nobody may order the queue, and Control
 * may do all of it.
 *
 * The secrecy: a Facility's stack depth is Secret (3.4.1, footnote 11) and a
 * card is face down until it is Active, so the Runners are told neither. A
 * Security player may not even see a run that has not gone in yet, because
 * budgets are set in Secret at the same moment targets are chosen.
 */
class PlayersDriveRunsTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Turn $turn;

    private Corporation $corporation;

    private Facility $facility;

    private FakeDice $dice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dice = new FakeDice;
        $this->app->instance(Dice::class, $this->dice);

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

        /** @var Turn $turn */
        $turn = $this->game->currentTurn();
        $this->turn = $turn;

        $this->corporation = Corporation::factory()->for($this->game)->create([
            'name' => 'Gordon',
            'credits' => 50,
        ]);

        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        $this->facility = Facility::factory()
            ->for($this->corporation)
            ->for($type)
            ->create(['name' => 'Attercliffe Yard']);
    }

    // ------------------------------------------------------------------
    // Submitting (3.4.1)
    // ------------------------------------------------------------------

    public function test_a_runner_may_put_in_for_a_run(): void
    {
        [$user, $runner] = $this->runner();

        $this->actingAs($user)
            ->post(route('runs.store'), [
                'facility_id' => $this->facility->id,
                'run_leader_character_id' => $runner->id,
            ])
            ->assertRedirect();

        $run = Run::query()->sole();

        $this->assertSame($this->facility->id, $run->facility_id);
        $this->assertSame($runner->id, $run->run_leader_character_id);
        $this->assertSame(RunStatus::Submitted, $run->status);
    }

    /**
     * 3.4 hands the Facility game to "Runners" as a side rather than to one
     * role, and a Freelancer is in a gang and goes on runs like anybody else.
     */
    public function test_a_freelancer_may_put_in_for_a_run(): void
    {
        [$user, $freelancer] = $this->runner(CharacterRole::Freelancer);

        $this->actingAs($user)
            ->post(route('runs.store'), [
                'facility_id' => $this->facility->id,
                'run_leader_character_id' => $freelancer->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, Run::query()->count());
    }

    public function test_a_corporate_player_may_not_put_in_for_a_run(): void
    {
        $security = $this->seat(CharacterRole::Security);
        [, $runner] = $this->runner();

        $this->actingAs($security)
            ->post(route('runs.store'), [
                'facility_id' => $this->facility->id,
                'run_leader_character_id' => $runner->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Run::query()->count());
    }

    // ------------------------------------------------------------------
    // Who may do what
    // ------------------------------------------------------------------

    public function test_only_the_run_leader_rolls(): void
    {
        [$leaderUser, $leader] = $this->runner();
        [$mateUser, $mate] = $this->runner();

        $run = $this->begun($leader, [$mate->id]);
        $this->activate($run);

        $this->actingAs($mateUser)
            ->post(route('runs.challenge', $run), [
                'skill' => RunnerSkill::Brawn->value,
                'printed_strength' => 2,
            ])
            ->assertForbidden();

        // Three dice each: the Leader's Brawn of 2 plus half the mate's 2, and
        // the card's printed 2 plus the 1 Alert a pair raises going in.
        $this->dice->will([8, 8, 8])->will([1, 1, 1]);

        $this->actingAs($leaderUser)
            ->post(route('runs.challenge', $run), [
                'skill' => RunnerSkill::Brawn->value,
                'printed_strength' => 2,
            ])
            ->assertRedirect();

        $this->assertSame(1, $run->refresh()->diceRolls->where('roller', 'runners')->count());
    }

    /**
     * "Each Runner, starting with the Run Leader, may take this opportunity to
     * leave" - each Runner's own decision, so a Runner who is not the Leader
     * may still walk away.
     */
    public function test_any_runner_on_the_run_may_walk_away(): void
    {
        [, $leader] = $this->runner();
        [$mateUser, $mate] = $this->runner();

        $run = $this->begun($leader, [$mate->id]);

        $this->actingAs($mateUser)
            ->post(route('runs.leave', $run), ['character_id' => $mate->id])
            ->assertRedirect();

        $this->assertCount(1, $run->refresh()->activeParticipants());
    }

    public function test_a_runner_who_is_not_on_the_run_may_not_touch_it(): void
    {
        [, $leader] = $this->runner();
        [$strangerUser, $stranger] = $this->runner();

        $run = $this->begun($leader);

        $this->actingAs($strangerUser)
            ->post(route('runs.leave', $run), ['character_id' => $stranger->id])
            ->assertForbidden();
        $this->actingAs($strangerUser)
            ->post(route('runs.advance', $run))
            ->assertForbidden();
    }

    public function test_security_works_the_cards_and_the_runners_do_not(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $security = $this->seat(CharacterRole::Security);

        $run = $this->begun($leader);

        $this->actingAs($leaderUser)
            ->post(route('runs.activate', $run))
            ->assertForbidden();

        $this->actingAs($security)
            ->post(route('runs.activate', $run))
            ->assertRedirect();

        $this->assertTrue(app(RunEngine::class)->cursor($run->refresh())->cardIsActive());
    }

    public function test_a_rival_corporations_security_player_may_not_defend(): void
    {
        [, $leader] = $this->runner();
        $rival = Corporation::factory()->for($this->game)->create(['name' => 'Dancers']);
        $rivalSecurity = $this->seat(CharacterRole::Security, $rival);

        $run = $this->begun($leader);

        $this->actingAs($rivalSecurity)
            ->post(route('runs.activate', $run))
            ->assertForbidden();
    }

    /**
     * The one ability no player has: a group that could order the queue could
     * put itself at the front of it.
     */
    public function test_no_player_may_order_the_queue_and_control_may(): void
    {
        [$leaderUser, $leader] = $this->runner();
        [, $other] = $this->runner();

        app(RunEngine::class)->submit($this->turn, $this->facility, $leader);
        app(RunEngine::class)->submit($this->turn, $this->facility, $other);

        $this->actingAs($leaderUser)
            ->post(route('runs.order', $this->facility))
            ->assertForbidden();

        $this->actingAs($this->seat(CharacterRole::Security))
            ->post(route('runs.order', $this->facility))
            ->assertForbidden();

        $this->dice->will([3, 6]);

        $this->actingAs($this->control())
            ->post(route('runs.order', $this->facility))
            ->assertRedirect();

        $this->assertSame(
            [1, 2],
            Run::query()->orderBy('order_index')->pluck('order_index')->all(),
        );
    }

    /**
     * A run must never stall on a player being away from their laptop, so
     * Control can do every act on either side.
     */
    public function test_control_may_act_for_either_side(): void
    {
        [, $leader] = $this->runner();
        $control = $this->control();

        $run = $this->begun($leader);

        $this->actingAs($control)->post(route('runs.activate', $run))->assertRedirect();

        $this->dice->will([8, 8])->will([1, 1]);
        $this->actingAs($control)
            ->post(route('runs.challenge', $run), [
                'skill' => RunnerSkill::Brawn->value,
                'printed_strength' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($control)->post(route('runs.advance', $run))->assertRedirect();

        $this->assertSame(RunStatus::Succeeded, $run->refresh()->status);
    }

    // ------------------------------------------------------------------
    // Secrecy (3.4.1)
    // ------------------------------------------------------------------

    /**
     * Footnote 11: "The number of Protection Cards that a Facility contains is
     * Secret." So the Runners are never told how many are left - they find out
     * by running out, which is what makes the Breather a real decision.
     */
    public function test_the_runners_are_not_told_how_deep_the_stack_is(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $this->card(ProtectionKind::Physical, 2);
        $this->card(ProtectionKind::Cyber, 1);

        $run = $this->begun($leader);

        $theirs = $this->boardFor($leaderUser)['yours'][0];
        $this->assertNull($theirs['cards_remaining']);

        $securitySees = $this->boardFor($this->seat(CharacterRole::Security))['defending'][0];
        $this->assertSame(3, $securitySees['cards_remaining']);
    }

    /**
     * A card is face down until it is flipped. Security is reading their own
     * stack, which 3.4.2 keeps Secret from everyone else and not from them.
     */
    public function test_a_card_is_nameless_to_the_runners_until_it_is_active(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $security = $this->seat(CharacterRole::Security);

        $run = $this->begun($leader);

        $beforehand = $this->boardFor($leaderUser)['yours'][0]['card'];
        $this->assertFalse($beforehand['active']);
        $this->assertArrayNotHasKey('name', $beforehand);
        $this->assertArrayNotHasKey('challenge', $beforehand);

        // Security can read the card they are deciding whether to pay for.
        $this->assertArrayHasKey('name', $this->boardFor($security)['defending'][0]['card']);

        $this->actingAs($security)->post(route('runs.activate', $run));

        $afterwards = $this->boardFor($leaderUser)['yours'][0]['card'];
        $this->assertTrue($afterwards['active']);
        $this->assertArrayHasKey('challenge', $afterwards);
    }

    /**
     * A card Security leaves switched off is one the Runners get past without
     * ever learning the name of, so the log line cannot name it either.
     */
    public function test_the_log_does_not_leak_a_card_security_left_switched_off(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $security = $this->seat(CharacterRole::Security);

        // Named before the run starts, because the fixture only installs a card
        // lazily and there would be nothing to rename yet.
        $this->card();
        $this->facility->protectionCards()->sole()
            ->cardType->forceFill(['name' => 'Roboscorpion'])->save();

        $this->facility->stateForTurn($this->turn)->forceFill(['security_directed' => true])->save();

        $run = $this->begun($leader);

        $this->actingAs($security)
            ->post(route('runs.activate', $run), ['activating' => false])
            ->assertRedirect();

        $runnersLog = collect($this->boardFor($leaderUser)['yours'][0]['log'])->pluck('description');
        $securityLog = collect($this->boardFor($security)['defending'][0]['log'])->pluck('description');

        $this->assertTrue($runnersLog->every(fn (string $line): bool => ! str_contains($line, 'Roboscorpion')));
        $this->assertTrue($securityLog->contains(fn (string $line): bool => str_contains($line, 'Roboscorpion')));
    }

    /**
     * Security sets budgets "in Secret at the same time as Runners are choosing
     * which Facilities to Run against", so a submitted run is invisible to them
     * until it actually goes in.
     */
    public function test_security_cannot_see_a_run_that_has_not_gone_in_yet(): void
    {
        [, $leader] = $this->runner();
        $security = $this->seat(CharacterRole::Security);

        $run = app(RunEngine::class)->submit($this->turn, $this->facility, $leader);

        $this->assertSame([], $this->boardFor($security)['defending']);

        app(RunEngine::class)->begin($run);

        $this->assertCount(1, $this->boardFor($security)['defending']);
    }

    public function test_a_runner_cannot_see_another_groups_run(): void
    {
        [, $leader] = $this->runner();
        [$otherUser, $other] = $this->runner();

        $this->begun($leader);
        $board = $this->boardFor($otherUser);

        $this->assertSame([], $board['yours']);
        $this->assertSame([], $board['defending']);
    }

    /**
     * The budget is how much defence is left in the Facility, so a Runner who
     * could read it would know when Security had run dry.
     */
    public function test_the_runners_cannot_read_the_security_budget(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $this->facility->stateForTurn($this->turn)->forceFill(['security_budget' => 7])->save();

        $this->begun($leader);

        $this->assertNull($this->boardFor($leaderUser)['yours'][0]['budget']);
        $this->assertSame(
            7,
            $this->boardFor($this->seat(CharacterRole::Security))['defending'][0]['budget']['placed'],
        );
    }

    // ------------------------------------------------------------------
    // The page
    // ------------------------------------------------------------------

    public function test_the_page_renders_with_the_targets_a_group_could_name(): void
    {
        [$user] = $this->runner();

        $this->actingAs($user)
            ->get(route('runs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('runs')
                ->where('board.can_submit', true)
                ->has('board.targets', 1)
                ->has('board.party', 1));
    }

    public function test_the_page_renders_for_a_signed_in_player_with_no_character(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('runs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('board.can_submit', false));
    }

    /**
     * A Facility still being built is not a target, because it is not open yet.
     */
    public function test_a_facility_still_being_built_is_not_offered_as_a_target(): void
    {
        [$user] = $this->runner();
        $this->facility->forceFill(['available_from_turn' => $this->turn->number + 5])->save();

        $this->actingAs($user)
            ->get(route('runs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('board.targets', 0));
    }

    /**
     * A Runner already out on a run is not offered as somebody to take with
     * you, rather than being offered and then refused.
     */
    public function test_a_runner_already_out_is_not_offered_to_a_second_group(): void
    {
        [$user, $runner] = $this->runner();
        [, $busy] = $this->runner();

        $this->begun($busy);

        $party = collect($this->boardFor($user)['party'])->pluck('id');

        $this->assertTrue($party->contains($runner->id));
        $this->assertFalse($party->contains($busy->id));
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * @return array{0: User, 1: Character}
     */
    private function runner(CharacterRole $role = CharacterRole::Runner): array
    {
        $user = User::factory()->create();

        /** @var Character $character */
        $character = Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => $role,
            'brawn' => 2,
            'hack' => 2,
        ]);

        return [$user, $character];
    }

    private function seat(CharacterRole $role, ?Corporation $corporation = null): User
    {
        $user = User::factory()->create();
        $corporation ??= $this->corporation;

        $corporation->characters()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'name' => $corporation->name.' '.$role->label(),
            'role' => $role,
        ]);

        return $user;
    }

    private function control(): User
    {
        $user = User::factory()->create();

        ControlMember::create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'discord_username' => 'control-'.$user->id,
            'name' => 'Control',
        ]);

        return $user;
    }

    /**
     * A run already under way, with one physical card in the Facility unless
     * the test has said otherwise.
     *
     * @param  array<int, int>  $memberIds
     */
    private function begun(Character $leader, array $memberIds = []): Run
    {
        if ($this->facility->protectionCards()->count() === 0) {
            $this->card();
        }

        $engine = app(RunEngine::class);

        return $engine->begin($engine->submit($this->turn, $this->facility, $leader, $memberIds));
    }

    private function card(ProtectionKind $kind = ProtectionKind::Physical, int $count = 1): void
    {
        $existing = $this->facility->protectionCards()->where('kind', $kind)->count();

        for ($position = 1; $position <= $count; $position++) {
            FacilityProtectionCard::factory()->create([
                'facility_id' => $this->facility->id,
                'protection_card_type_id' => ProtectionCardType::factory()
                    ->ofKind($kind)
                    ->create(['game_id' => $this->game->id])->id,
                'kind' => $kind,
                'position' => $existing + $position,
            ]);
        }
    }

    private function activate(Run $run): void
    {
        app(RunEngine::class)->activate($run);
    }

    /**
     * The run board as one user sees it.
     *
     * @return array<string, mixed>
     */
    private function boardFor(User $user): array
    {
        return app(RunPresenter::class)->forPlayer($this->game->refresh(), $user);
    }
}
