<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\ProtectionKind;
use App\Enums\RunAccessKind;
use App\Enums\RunConsequence;
use App\Enums\RunnerSkill;
use App\Enums\RunStatus;
use App\Enums\RunStep;
use App\Enums\TechnologyHoldingStatus;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\Run;
use App\Models\RunEvent;
use App\Models\TechnologyHolding;
use App\Models\Turn;
use App\Models\User;
use App\Services\Dice;
use App\Services\RunEngine;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use App\Support\RunPresenter;
use App\Support\Runs\ChallengeStrength;
use App\Support\Runs\ConsequenceSlip;
use App\Support\Runs\DicePool;
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

        // The card's printed 2 plus the 1 Alert a pair raises going in.
        $this->dice->will([1, 1, 1]);
        app(RunEngine::class)->defend($run->refresh(), 2);

        $this->actingAs($mateUser)
            ->post(route('runs.challenge', $run), [
                'skill' => RunnerSkill::Brawn->value,
            ])
            ->assertForbidden();

        // The Leader's Brawn of 2 plus half the mate's 2.
        $this->dice->will([8, 8, 8]);

        $this->actingAs($leaderUser)
            ->post(route('runs.challenge', $run), [
                'skill' => RunnerSkill::Brawn->value,
            ])
            ->assertRedirect();

        $this->assertSame(1, $run->refresh()->diceRolls->where('roller', 'runners')->count());
    }

    /**
     * Each Runner spends their own access, and nobody else's (3.4.3).
     *
     * `act` asks whether the caller is on the run, which every Runner on it
     * passes - so without a second check a Runner could spend a gangmate's
     * access out from under them, which is the one thing per-Runner accesses
     * exist to prevent.
     */
    public function test_a_runner_spends_their_own_access_and_control_spends_anybodys(): void
    {
        [$leaderUser, $leader] = $this->runner();
        [$mateUser, $mate] = $this->runner();

        $run = $this->begun($leader, [$mate->id]);
        $this->succeed($run);

        // The Leader cannot spend the mate's access.
        $this->actingAs($leaderUser)
            ->post(route('runs.accesses.store', $run), [
                'character_id' => $mate->id,
                'kind' => RunAccessKind::Credits->value,
            ])
            ->assertForbidden();

        // Their own, they may.
        $this->actingAs($leaderUser)
            ->post(route('runs.accesses.store', $run), [
                'character_id' => $leader->id,
                'kind' => RunAccessKind::Credits->value,
            ])
            ->assertRedirect();

        // And the Credits card is gone, so the mate is refused it.
        $this->actingAs($mateUser)
            ->post(route('runs.accesses.store', $run), [
                'character_id' => $mate->id,
                'kind' => RunAccessKind::Credits->value,
            ])
            ->assertSessionHasErrors('access');

        // Control acts for anybody, because a run must not stall on a player
        // being away from their laptop.
        $this->actingAs($this->control())
            ->post(route('runs.accesses.store', $run), [
                'character_id' => $mate->id,
                'kind' => RunAccessKind::Plot->value,
            ])
            ->assertRedirect();

        $this->assertSame(2, $run->refresh()->accesses()->count());
    }

    /**
     * A card access is two requests, and it has to be: the card is drawn and
     * then decided on. Deciding before the draw would be picking how to open a
     * safe before knowing what is in it.
     */
    public function test_a_card_is_drawn_first_and_decided_on_afterwards(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $run = $this->begun($leader);
        $this->succeed($run);

        TechnologyHolding::factory()->create([
            'game_id' => $run->game_id,
            'facility_id' => $run->facility_id,
            'status' => TechnologyHoldingStatus::Claimed,
        ]);

        // No action is named here, and none is wanted.
        $this->actingAs($leaderUser)
            ->post(route('runs.accesses.store', $run), [
                'character_id' => $leader->id,
                'kind' => RunAccessKind::Technology->value,
            ])
            ->assertRedirect();

        $access = $run->refresh()->accesses()->sole();
        $this->assertNotNull($access->technology_holding_id);
        $this->assertNull($access->outcome);

        // And the board offers it back as undecided.
        $board = $this->boardFor($leaderUser)['yours'][0];
        $this->assertCount(1, $board['accesses']['undecided']);

        $this->actingAs($leaderUser)
            ->post(route('runs.accesses.resolve', [$run, $access]), [])
            ->assertRedirect();

        $this->assertSame('left', $access->refresh()->outcome);
        $this->assertSame([], $this->boardFor($leaderUser)['yours'][0]['accesses']['undecided']);
    }

    /**
     * A Corporate player is not on the run and takes nothing out of it.
     */
    public function test_security_cannot_spend_an_access_on_their_own_facility(): void
    {
        [, $leader] = $this->runner();

        $run = $this->begun($leader);
        $this->succeed($run);

        $this->actingAs($this->seat(CharacterRole::Security))
            ->post(route('runs.accesses.store', $run), [
                'character_id' => $leader->id,
                'kind' => RunAccessKind::Credits->value,
            ])
            ->assertForbidden();
    }

    /**
     * Security rolls the card's defence, and the Runners cannot do it for them.
     *
     * Two acts rather than one, because they are two people throwing dice:
     * Security is holding the card and names the strength printed on it, and
     * the Runners then throw theirs against what Security actually got.
     */
    public function test_security_rolls_the_defence_and_the_runners_roll_after_it(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $security = $this->seat(CharacterRole::Security);

        $run = $this->begun($leader);
        $this->activate($run);

        // The Runners cannot roll until Security has.
        $this->actingAs($leaderUser)
            ->post(route('runs.challenge', $run), ['skill' => RunnerSkill::Brawn->value])
            ->assertSessionHasErrors('challenge');

        // Nor can they roll the defence themselves.
        $this->actingAs($leaderUser)
            ->post(route('runs.defend', $run), ['printed_strength' => 2])
            ->assertForbidden();

        $this->dice->will([8, 8]);
        $this->actingAs($security)
            ->post(route('runs.defend', $run), ['printed_strength' => 2])
            ->assertRedirect();

        // Security's two successes are on record before the Runners throw, and
        // the run is waiting at the Challenge step for them.
        $defence = $run->events()->where('type', RunEvent::TYPE_DEFENDED)->sole();
        $this->assertSame(2, $defence->payload['security_successes']);
        $this->assertSame(
            RunStep::Challenge,
            app(RunEngine::class)->cursor($run->refresh())->step,
        );

        // And Security cannot roll a second time for the same card.
        $this->actingAs($security)
            ->post(route('runs.defend', $run), ['printed_strength' => 2])
            ->assertSessionHasErrors('defend');

        $this->dice->will([8, 8]);
        $this->actingAs($leaderUser)
            ->post(route('runs.challenge', $run), ['skill' => RunnerSkill::Brawn->value])
            ->assertRedirect();

        // A tie goes to Security, and exactly one roll is kept for each side.
        $this->assertSame(1, $run->refresh()->diceRolls->where('roller', 'security')->count());
        $this->assertSame(1, $run->refresh()->diceRolls->where('roller', 'runners')->count());
        $this->assertSame(
            RunStep::Consequence,
            app(RunEngine::class)->cursor($run->refresh())->step,
        );
    }

    /**
     * The challenge compares against the dice Security actually threw, not a
     * strength worked out again afterwards.
     *
     * Which matters because the strength moves: Security spending Alerts
     * between the two rolls lowers the Alert bonus, and re-deriving it at the
     * Runners' roll would quietly change the number the defence was rolled on.
     */
    public function test_the_challenge_is_judged_on_the_roll_security_actually_made(): void
    {
        [$leaderUser, $leader] = $this->runner();

        $run = $this->begun($leader);
        $this->activate($run);

        $this->dice->will([8, 8, 8]);
        app(RunEngine::class)->defend($run->refresh(), 3);

        // Two successes for the Runners against Security's three.
        $this->dice->will([8, 8, 1]);
        $this->actingAs($leaderUser)
            ->post(route('runs.challenge', $run), ['skill' => RunnerSkill::Brawn->value])
            ->assertRedirect();

        $challenge = $run->events()->where('type', RunEvent::TYPE_CHALLENGE)->sole();
        $this->assertSame(3, $challenge->payload['security_successes']);
        $this->assertFalse($challenge->payload['runners_won']);
        $this->assertSame(3, $challenge->payload['strength']['printed']);
    }

    /**
     * A card prints "1 alert, 1 wound" and that is one consequence with two
     * parts, not two consequences. It has to be possible to take both.
     *
     * The bug this pins: the cursor is derived from the log, and the *first*
     * consequence event moves the run to the Breather - so applying the parts
     * one request at a time offered the Leader exactly one of them and the rest
     * of the card's sentence went unpaid. Security marks the whole card and the
     * Leader takes the whole card.
     */
    public function test_a_card_printing_several_consequences_applies_all_of_them(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $securityUser = $this->seat(CharacterRole::Security);
        $run = $this->begun($leader);
        $this->atConsequence($run);

        $alertsBefore = $run->refresh()->alerts;

        $this->actingAs($securityUser)
            ->post(route('runs.consequences.mark', $run), [
                'effects' => [
                    RunConsequence::Alert->value => 2,
                    RunConsequence::Wound->value => 1,
                ],
            ])
            ->assertRedirect();

        $this->actingAs($leaderUser)
            ->post(route('runs.consequences.store', $run), [
                'character_id' => $leader->id,
            ])
            ->assertRedirect();

        $this->assertSame($alertsBefore + 2, $run->refresh()->alerts);
        $this->assertSame(1, $leader->refresh()->wounds);

        // One line each in the log, so the record reads like the card.
        $this->assertSame(2, $run->events()->where('type', RunEvent::TYPE_CONSEQUENCE)->count());
    }

    /**
     * A card that prints damage *and* an End the Run does both, in that order:
     * the Runners take what the card does to them and then the run stops. The
     * End the Run is answered last because it is the one part of the slip that
     * is a question rather than a consequence.
     */
    public function test_a_card_that_ends_the_run_lands_its_damage_first(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $securityUser = $this->seat(CharacterRole::Security);
        $run = $this->begun($leader);
        $this->atConsequence($run);

        $this->actingAs($securityUser)
            ->post(route('runs.consequences.mark', $run), [
                'effects' => [
                    RunConsequence::Wound->value => 1,
                    RunConsequence::EndTheRun->value => 1,
                ],
            ])
            ->assertRedirect();

        $this->actingAs($leaderUser)
            ->post(route('runs.consequences.store', $run), [
                'character_id' => $leader->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, $leader->refresh()->wounds);
        $this->assertSame(RunStatus::Failed, $run->refresh()->status);
    }

    /**
     * The other answer to an End the Run: take Wounds, Tags and an Alert
     * instead and face the card again (3.4.2). The price is the number already
     * ignored plus this one, so the first costs 1 of each.
     */
    public function test_the_run_leader_may_ignore_an_end_the_run(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $securityUser = $this->seat(CharacterRole::Security);
        $run = $this->begun($leader);
        $this->atConsequence($run);

        $alertsBefore = $run->refresh()->alerts;

        $this->actingAs($securityUser)
            ->post(route('runs.consequences.mark', $run), [
                'effects' => [RunConsequence::EndTheRun->value => 1],
            ])
            ->assertRedirect();

        $this->actingAs($leaderUser)
            ->post(route('runs.consequences.store', $run), [
                'character_id' => $leader->id,
                'ignore_end_the_run' => true,
            ])
            ->assertRedirect();

        $run = $run->refresh();
        $leader = $leader->refresh();

        $this->assertSame(RunStatus::Running, $run->status);
        $this->assertSame(1, $run->ignored_end_the_run);
        $this->assertTrue($run->retry_pending);
        $this->assertSame(1, $leader->wounds);
        $this->assertSame(1, $leader->tags);
        $this->assertSame($alertsBefore + 1, $run->alerts);
    }

    /**
     * The card is Security's to read, so the Run Leader cannot write down what
     * it does - and Security cannot decide who takes it. Two halves, two
     * seats, which is the whole point of the handshake.
     */
    public function test_the_run_leader_cannot_mark_the_card(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $run = $this->begun($leader);
        $this->atConsequence($run);

        $this->actingAs($leaderUser)
            ->post(route('runs.consequences.mark', $run), [
                'effects' => [RunConsequence::Wound->value => 9],
            ])
            ->assertForbidden();
    }

    public function test_security_cannot_decide_who_takes_it(): void
    {
        [, $leader] = $this->runner();
        $securityUser = $this->seat(CharacterRole::Security);
        $run = $this->begun($leader);
        $this->atConsequence($run);

        $this->mark($run, [RunConsequence::Wound->value => 1]);

        $this->actingAs($securityUser)
            ->post(route('runs.consequences.store', $run), [
                'character_id' => $leader->id,
            ])
            ->assertForbidden();
    }

    /**
     * And the Leader cannot take a consequence nobody has written down, which
     * is what stops the old form's numbers being invented at this end.
     */
    public function test_nothing_can_be_taken_before_security_marks_it(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $run = $this->begun($leader);
        $this->atConsequence($run);

        $this->actingAs($leaderUser)
            ->post(route('runs.consequences.store', $run), [
                'character_id' => $leader->id,
            ])
            ->assertSessionHasErrors('consequence');

        $this->assertSame(0, $leader->refresh()->wounds);
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

        // Control rolls both sides, because a run must not stall on either of
        // them being away: Security's defence first, then the Runners'.
        $this->dice->will([1, 1]);
        $this->actingAs($control)
            ->post(route('runs.defend', $run), ['printed_strength' => 2])
            ->assertRedirect();

        $this->dice->will([8, 8]);
        $this->actingAs($control)
            ->post(route('runs.challenge', $run), [
                'skill' => RunnerSkill::Brawn->value,
            ])
            ->assertRedirect();

        $this->actingAs($control)->post(route('runs.advance', $run))->assertRedirect();

        $this->assertSame(RunStatus::Succeeded, $run->refresh()->status);
    }

    // ------------------------------------------------------------------
    // Secrecy (3.4.1)
    // ------------------------------------------------------------------

    /**
     * The Alerts a group raises for its size are quoted by the server, for the
     * reason the dice pool is: the browser kept a copy of the printed list and
     * said nobody knew for anything past it.
     */
    public function test_the_group_size_alert_curve_is_quoted_by_the_server(): void
    {
        [$user] = $this->runner();

        // Only sizes a group could actually be are quoted, so the roster has
        // to be big enough for seven before seven is one of them.
        for ($i = 0; $i < 6; $i++) {
            $this->runner();
        }

        $alerts = app(RunPresenter::class)->forPlayer($this->game->refresh(), $user)['group_alerts'];

        // The printed list...
        $this->assertSame(0, $alerts[1]);
        $this->assertSame(11, $alerts[6]);

        // ...and the triangular numbers after it.
        $this->assertSame(18, $alerts[7]);
    }

    /**
     * 3.4.5 asks players to work their contribution out in advance, because the
     * Action phase is fifteen minutes long. The server does it instead, and it
     * does it for both skills: plenty of cards offer the choice, and the card's
     * own sentence is never parsed into a column.
     *
     * Quoted rather than computed in the browser for the reason a reorder cost
     * is: half rounded down while healthy and a quarter rounded *up* while
     * Wounded is one rule, and two implementations of it would disagree about a
     * die sooner or later.
     */
    public function test_the_pool_the_runners_would_throw_is_quoted_by_the_server(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $leader->forceFill(['brawn' => 5, 'hack' => 3])->save();

        [, $mate] = $this->runner();
        $mate->forceFill(['brawn' => 5, 'hack' => 1])->save();

        [, $hurt] = $this->runner();
        $hurt->forceFill(['brawn' => 5, 'hack' => 1, 'wounds' => 1])->save();

        $this->begun($leader, [$mate->id, $hurt->id]);

        $pool = $this->boardFor($leaderUser)['yours'][0]['dice_pool'];

        // Brawn: the Leader's full 5, half of the healthy mate's 5 rounded down
        // (2), and a quarter of the Wounded one's 5 rounded up (2).
        $this->assertSame(5, $pool['brawn']['leader']);
        $this->assertSame(2, $pool['brawn']['others'][$mate->id]);
        $this->assertSame(2, $pool['brawn']['others'][$hurt->id]);
        $this->assertSame(9, $pool['brawn']['total']);

        // Hack: a healthy 1 brings nothing, a Wounded 1 still brings a die.
        // The rounding goes opposite ways on purpose.
        $this->assertSame(0, $pool['hack']['others'][$mate->id]);
        $this->assertSame(1, $pool['hack']['others'][$hurt->id]);
        $this->assertSame(4, $pool['hack']['total']);

        // The die size is the Leader's alone: they are unwounded, so d8s, even
        // though somebody else on the run is hurt.
        $this->assertSame(8, $pool['brawn']['die_faces']);
        $this->assertSame(8, $pool['hack']['die_faces']);
    }

    /**
     * A pool of no dice and no pool at all are different answers, and the
     * second is what a run with no Leader standing has.
     */
    public function test_a_run_with_no_leader_left_quotes_no_pool(): void
    {
        [$leaderUser, $leader] = $this->runner();
        $run = $this->begun($leader);

        app(RunEngine::class)->leave($run, $leader);

        $this->assertSame([], $this->boardFor($leaderUser)['yours'][0]['dice_pool']);
    }

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
    /**
     * Get the group inside, so there is something to access.
     *
     * Control's routes rather than the engine, because that is the shortest
     * honest way to a successful run: the point of these tests is what happens
     * once the Runners are in.
     */
    private function succeed(Run $run): void
    {
        $run->forceFill([
            'status' => RunStatus::Succeeded,
            'ended_at' => now(),
        ])->save();
    }

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
     * A run at the Consequence step, which is the only way to reach one: the
     * card comes on, Security rolls, and the Runners fail to break it.
     *
     * Both pools are asked for rather than written out, for the reason
     * RunEngineTest's walkPast() does it - a strength or a group size the test
     * guessed at would run the fake dice dry somewhere else entirely.
     */
    private function atConsequence(Run $run, RunnerSkill $skill = RunnerSkill::Brawn): void
    {
        $this->activate($run);

        $engine = app(RunEngine::class);
        $run = $run->refresh();
        $cursor = $engine->cursor($run);

        $strength = ChallengeStrength::for(
            printed: 1,
            cardsPassed: $run->active_cards_passed,
            alerts: $run->alertsAvailable(),
            boosts: $cursor->activation?->boosts ?? 0,
        );

        $this->dice->willRoll($strength->total(), 8);
        $engine->defend($run, 1);

        $pool = DicePool::for(
            leaderSkill: (int) $run->leader?->getAttribute($skill->column()),
            leaderWounded: ($run->leader?->wounds ?? 0) > 0,
            others: $run->activeParticipants()
                ->reject(fn ($p): bool => $p->character_id === $run->run_leader_character_id)
                ->mapWithKeys(fn ($p): array => [$p->character_id => [
                    'skill' => (int) $p->character->getAttribute($skill->column()),
                    'wounded' => $p->character->wounds > 0,
                ]])
                ->all(),
        );

        $this->dice->willRoll($pool->total(), 1);
        $engine->challenge($run->refresh(), $skill);
    }

    /**
     * Security writing the card down, which is what the Run Leader then takes.
     *
     * @param  array<string, int>  $effects
     */
    private function mark(Run $run, array $effects): void
    {
        app(RunEngine::class)->markConsequence($run->refresh(), ConsequenceSlip::of($effects));
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
