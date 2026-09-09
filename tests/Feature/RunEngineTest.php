<?php

namespace Tests\Feature;

use App\Enums\ProtectionKind;
use App\Enums\RunConsequence;
use App\Enums\RunDeparture;
use App\Enums\RunnerSkill;
use App\Enums\RunStatus;
use App\Enums\RunStep;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\Run;
use App\Models\RunEvent;
use App\Models\TrackerAdjustment;
use App\Models\Turn;
use App\Services\Dice;
use App\Services\RunEngine;
use App\Support\Runs\ChallengeStrength;
use App\Support\Runs\DicePool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeDice;
use Tests\TestCase;

/**
 * The Run loop (rulebook 3.4.1, 3.4.2, 3.4.4).
 *
 * The dice are faked throughout, because a test that cannot say what the dice
 * did can only assert that something happened - and what is worth asserting
 * here is the consequence of a particular roll, not that a roll occurred.
 */
class RunEngineTest extends TestCase
{
    use RefreshDatabase;

    private FakeDice $dice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dice = new FakeDice;
        $this->app->instance(Dice::class, $this->dice);
    }

    private function engine(): RunEngine
    {
        return $this->app->make(RunEngine::class);
    }

    // ------------------------------------------------------------------
    // Submitting and ordering (3.4.1)
    // ------------------------------------------------------------------

    public function test_a_run_is_submitted_with_its_leader_first(): void
    {
        [$turn, $facility] = $this->facility();
        $leader = $this->runner($turn->game_id);
        $mate = $this->runner($turn->game_id);

        $run = $this->engine()->submit($turn, $facility, $leader, [$mate->id]);

        $this->assertSame(RunStatus::Submitted, $run->status);
        $this->assertSame($leader->id, $run->run_leader_character_id);
        $this->assertSame(
            [$leader->id, $mate->id],
            $run->participants->pluck('character_id')->all(),
        );
        $this->assertSame([1, 2], $run->participants->pluck('position')->all());
        $this->assertSame(RunEvent::TYPE_SUBMITTED, $run->events->first()?->type);
    }

    /**
     * A solo Runner is their own Run Leader (3.4), so naming them twice is not
     * an error - it is the same person.
     */
    public function test_a_solo_runner_leads_their_own_run(): void
    {
        [$turn, $facility] = $this->facility();
        $leader = $this->runner($turn->game_id);

        $run = $this->engine()->submit($turn, $facility, $leader, [$leader->id]);

        $this->assertCount(1, $run->participants);
    }

    public function test_a_runner_cannot_be_on_two_runs_in_one_turn(): void
    {
        [$turn, $facility] = $this->facility();
        $leader = $this->runner($turn->game_id);
        $other = $this->runner($turn->game_id);

        $this->engine()->submit($turn, $facility, $leader);

        $this->expectException(ValidationException::class);

        $this->engine()->submit($turn, $facility, $other, [$leader->id]);
    }

    public function test_a_facility_still_being_built_cannot_be_run_against(): void
    {
        [$turn, $facility] = $this->facility();
        $facility->forceFill(['available_from_turn' => $turn->number + 1])->save();

        $this->expectException(ValidationException::class);

        $this->engine()->submit($turn, $facility, $this->runner($turn->game_id));
    }

    /**
     * The fewest Runners go first (3.4.1, priority 1), and the reason is
     * written down because priority 7 is a d8 and so the order cannot be
     * recomputed after the fact.
     */
    public function test_ordering_puts_the_smallest_group_first_and_says_why(): void
    {
        [$turn, $facility] = $this->facility();

        $solo = $this->engine()->submit($turn, $facility, $this->runner($turn->game_id));
        $pair = $this->engine()->submit($turn, $facility, $this->runner($turn->game_id), [
            $this->runner($turn->game_id)->id,
        ]);

        // One d8 per group, thrown up front.
        $this->dice->will([4, 7]);

        $ordered = $this->engine()->orderRuns($facility, $turn);

        $this->assertSame([$solo->id, $pair->id], $ordered->pluck('id')->all());
        $this->assertSame(1, $solo->refresh()->order_index);
        $this->assertSame('Fewest Runners', $solo->order_reason);
        $this->assertSame(2, $pair->refresh()->order_index);
    }

    // ------------------------------------------------------------------
    // Alerts at the start (3.4.1)
    // ------------------------------------------------------------------

    public function test_beginning_a_run_raises_alerts_from_tags_and_group_size(): void
    {
        [$turn, $facility] = $this->facility();
        $leader = $this->runner($turn->game_id, ['tags' => 2]);
        $mate = $this->runner($turn->game_id, ['tags' => 1]);

        $run = $this->engine()->submit($turn, $facility, $leader, [$mate->id]);
        $run = $this->engine()->begin($run);

        // Three Tags between them, plus 1 for being a group of two.
        $this->assertSame(4, $run->alerts);
        $this->assertSame(RunStatus::Running, $run->status);
        $this->assertNotNull($run->started_at);
    }

    /**
     * Alerts come from the Tags held "at the beginning of the Run", so a Tag
     * taken during it makes the *next* run worse rather than this one.
     */
    public function test_a_tag_taken_during_a_run_does_not_raise_its_alerts(): void
    {
        $run = $this->started(tags: 0);
        $this->assertSame(0, $run->alerts);

        $this->engine()->applyConsequence($run, RunConsequence::Tag, $run->leader);

        $this->assertSame(0, $run->refresh()->alerts);
        $this->assertSame(1, $run->leader?->refresh()->tags);
    }

    // ------------------------------------------------------------------
    // Activate (3.4.2)
    // ------------------------------------------------------------------

    public function test_a_physical_card_is_free_and_cyber_cards_cost_the_active_cyber_cards(): void
    {
        $run = $this->started(physical: 1, cyber: 2);
        $this->budget($run, 5);

        $physical = $this->engine()->activate($run);
        $this->assertSame(0, $physical->activation_cost);
        $this->assertTrue($physical->isActive());

        $this->walkPast($run);

        // First cyber card: free, because none are Active yet.
        $firstCyber = $this->engine()->activate($run->refresh());
        $this->assertSame(0, $firstCyber->activation_cost);

        $this->walkPast($run->refresh());

        // Second cyber card: one Active cyber card already, so 1 Credit.
        $secondCyber = $this->engine()->activate($run->refresh());
        $this->assertSame(1, $secondCyber->activation_cost);
        $this->assertSame(1, $run->facility->stateForTurn($run->turn)->refresh()->security_budget_spent);
    }

    /**
     * "If they are unable to pay the cost from the budget provided at the
     * beginning of the Action Phase then the card will remain Inactive."
     */
    public function test_security_that_cannot_pay_leaves_the_card_inactive(): void
    {
        $run = $this->started(physical: 0, cyber: 2);
        $this->budget($run, 0);

        $this->engine()->activate($run);
        $this->walkPast($run->refresh());

        $second = $this->engine()->activate($run->refresh());

        $this->assertFalse($second->isActive());
        $this->assertSame(1, $second->activation_cost);
        $this->assertSame(
            RunEvent::TYPE_ACTIVATION_FAILED,
            $run->refresh()->events->last()?->type,
        );
    }

    public function test_only_a_directing_security_player_may_leave_a_card_switched_off(): void
    {
        $run = $this->started();

        $this->expectException(ValidationException::class);

        $this->engine()->activate($run, activating: false);
    }

    public function test_a_directing_security_player_may_leave_a_card_switched_off(): void
    {
        $run = $this->started();
        $run->facility->stateForTurn($run->turn)->forceFill(['security_directed' => true])->save();

        $activation = $this->engine()->activate($run, activating: false);

        $this->assertFalse($activation->isActive());
        $this->assertSame(RunEvent::TYPE_ACTIVATION_DECLINED, $run->refresh()->events->last()?->type);
    }

    /**
     * An Inactive card is walked straight past: "If it is not [Active], then
     * they move on to the Breather step."
     */
    public function test_an_inactive_card_skips_to_the_breather(): void
    {
        $run = $this->started();
        $run->facility->stateForTurn($run->turn)->forceFill(['security_directed' => true])->save();

        $this->engine()->activate($run, activating: false);

        $this->assertSame(RunStep::Breather, $this->engine()->cursor($run->refresh())->step);

        $this->expectException(ValidationException::class);

        $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);
    }

    /**
     * A card an earlier group switched on is Active when the next group
     * arrives, and they pay nothing for it - which is the whole reason the
     * order at a Facility matters.
     */
    public function test_a_later_group_meets_a_card_the_first_group_switched_on(): void
    {
        $first = $this->started(cyber: 1, physical: 0);
        $this->budget($first, 5);
        $this->engine()->activate($first);

        $second = $this->engine()->submit(
            $first->turn,
            $first->facility,
            $this->runner($first->game_id),
        );
        $second = $this->engine()->begin($second);

        $cursor = $this->engine()->cursor($second);

        $this->assertTrue($cursor->cardIsActive());
        $this->assertTrue($cursor->activationSettled());

        // And there is nothing left for the second group's Security to do.
        $this->expectException(ValidationException::class);

        $this->engine()->activate($second);
    }

    public function test_boosting_needs_directing_security(): void
    {
        $run = $this->started();
        $this->engine()->activate($run);

        $this->expectException(ValidationException::class);

        $this->engine()->boost($run->refresh());
    }

    public function test_boosts_are_cumulative_per_card_and_add_strength(): void
    {
        $run = $this->started();
        $this->budget($run, 10);
        $run->facility->stateForTurn($run->turn)->forceFill(['security_directed' => true])->save();

        $this->engine()->activate($run);

        // 1 Credit for the first Boost, 2 for the second.
        $activation = $this->engine()->boost($run->refresh(), times: 2);

        $this->assertSame(2, $activation->boosts);
        $this->assertSame(3, $activation->boost_credits_spent);
        $this->assertSame(3, $activation->nextBoostCost());
        $this->assertSame(3, $run->facility->stateForTurn($run->turn)->refresh()->security_budget_spent);

        // Printed 2 plus two Boosts is 4 dice for Security.
        $this->dice->willRoll(2, 1)->willRoll(4, 8);

        $outcome = $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);

        $this->assertSame(4, $outcome->strength->total());
        $this->assertSame(2, $outcome->strength->fromBoosts);
    }

    // ------------------------------------------------------------------
    // Challenge (3.4.2)
    // ------------------------------------------------------------------

    /**
     * "The Run Leader rolls dice equal to their skill... If there are other
     * Runners taking part... they add half their skill to the pool (rounding
     * down). If they are Wounded, they add a quarter of their skill (rounding
     * up)."
     */
    public function test_the_pool_is_the_leaders_skill_plus_a_share_of_everyone_elses(): void
    {
        [$turn, $facility] = $this->facility(physical: 1);
        $leader = $this->runner($turn->game_id, ['brawn' => 5]);
        $healthy = $this->runner($turn->game_id, ['brawn' => 4]);
        $hurt = $this->runner($turn->game_id, ['brawn' => 5, 'wounds' => 1, 'body' => 4]);

        $run = $this->engine()->submit($turn, $facility, $leader, [$healthy->id, $hurt->id]);
        $run = $this->engine()->begin($run);
        $this->engine()->activate($run);

        // 5 from the Leader, 2 from the healthy 4, 2 from the Wounded 5. A
        // group of three opens on 2 Alerts, which is +1 on the printed 2.
        $this->dice->willRoll(9, 1)->willRoll(3, 1);

        $outcome = $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);

        $this->assertSame(9, $outcome->pool->total());
        $this->assertSame(5, $outcome->pool->fromLeader);
        $this->assertSame([$healthy->id => 2, $hurt->id => 2], $outcome->pool->fromOthers);
        // The Leader is not Wounded, so the whole pool is d8s.
        $this->assertSame(DicePool::HEALTHY_DIE, $outcome->pool->dieFaces);
    }

    /**
     * "If the Run Leader has at least one Wound then they roll d6s" - and the
     * others "add more dice to the pool", so one pool means one kind of die.
     */
    public function test_a_wounded_leader_drops_the_whole_pool_to_d6s(): void
    {
        [$turn, $facility] = $this->facility(physical: 1);
        $leader = $this->runner($turn->game_id, ['brawn' => 3, 'wounds' => 1, 'body' => 4]);
        $mate = $this->runner($turn->game_id, ['brawn' => 8]);

        $run = $this->engine()->submit($turn, $facility, $leader, [$mate->id]);
        $run = $this->engine()->begin($run);
        $this->engine()->activate($run);

        // 3 from the Leader and 4 from the mate's 8. A pair opens on 1 Alert,
        // which is +1 on the printed 2.
        $this->dice->willRoll(7, 1)->willRoll(3, 1);

        $outcome = $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);

        $this->assertSame(DicePool::WOUNDED_DIE, $outcome->pool->dieFaces);
        $this->assertSame(6, $outcome->runnersRoll->die_faces);
        // Security always rolls d8s.
        $this->assertSame(8, $outcome->securityRoll->die_faces);
    }

    public function test_a_tie_goes_to_security(): void
    {
        $run = $this->started();
        $this->engine()->activate($run);

        // Two successes each.
        $this->dice->will([8, 8])->will([8, 8]);

        $outcome = $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);

        $this->assertSame(2, $outcome->runnersRoll->successes);
        $this->assertSame(2, $outcome->securityRoll->successes);
        $this->assertFalse($outcome->runnersWon);
        $this->assertSame(RunStep::Consequence, $this->engine()->cursor($run->refresh())->step);
    }

    public function test_both_rolls_are_kept_with_every_face(): void
    {
        $run = $this->started();
        $this->engine()->activate($run);

        $this->dice->will([1, 2])->will([5, 6]);

        $outcome = $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);

        $this->assertSame([1, 2], $outcome->runnersRoll->faces);
        $this->assertSame('2d8, 5+ — 1,2 (0 successes)', $outcome->runnersRoll->readout());
        $this->assertSame('2d8, 5+ — 5,6 (2 successes)', $outcome->securityRoll->readout());
        $this->assertCount(2, $run->refresh()->diceRolls);
        // Both hang off the event that describes the check.
        $this->assertSame($outcome->event->id, $outcome->runnersRoll->run_event_id);
    }

    public function test_a_card_cannot_be_challenged_twice_in_one_pass(): void
    {
        $run = $this->started();
        $this->engine()->activate($run);

        $this->dice->will([8, 8])->will([1, 1]);
        $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);

        $this->expectException(ValidationException::class);

        $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);
    }

    /**
     * The strength bonus is "for each 2 Active Protection Cards already
     * passed", so a card Security never switched on makes nothing harder even
     * though the Runners got past it.
     */
    public function test_only_active_cards_passed_add_strength(): void
    {
        $run = $this->started(physical: 3);
        $run->facility->stateForTurn($run->turn)->forceFill(['security_directed' => true])->save();

        // Two cards left switched off, and walked straight past.
        $this->engine()->activate($run, activating: false);
        $this->engine()->advance($run->refresh());
        $this->engine()->activate($run->refresh(), activating: false);
        $this->engine()->advance($run->refresh());

        $run = $run->refresh();
        $this->assertSame(2, $run->cards_passed);
        $this->assertSame(0, $run->active_cards_passed);

        $this->engine()->activate($run, activating: true);
        $this->dice->willRoll(2, 1)->willRoll(2, 8);

        $outcome = $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);

        $this->assertSame(0, $outcome->strength->fromCardsPassed);
        $this->assertSame(2, $outcome->strength->total());
    }

    // ------------------------------------------------------------------
    // Consequence (3.4.2)
    // ------------------------------------------------------------------

    /**
     * The surprising one, and the reason a Facility is attrition rather than a
     * wall: 3.4.2 sends the Runners to the Breather "unless a Protection Card
     * has an 'End the Run' consequence", so losing a check costs them the
     * consequence and they still get past the card.
     */
    public function test_failing_a_challenge_still_passes_the_card(): void
    {
        $run = $this->started(physical: 2);
        $this->engine()->activate($run);

        $this->dice->will([1, 1])->will([8, 8]);
        $outcome = $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);
        $this->assertFalse($outcome->runnersWon);

        $this->engine()->applyConsequence($run->refresh(), RunConsequence::Wound, $run->leader);
        $run = $this->engine()->advance($run->refresh());

        $this->assertSame(1, $run->cards_passed);
        $this->assertSame(1, $run->active_cards_passed);
        $this->assertSame(RunStatus::Running, $run->status);
        $this->assertSame(2, $this->engine()->cursor($run)->pass);
    }

    public function test_a_wound_goes_through_the_tracker_ledger(): void
    {
        $run = $this->started(['body' => 5]);

        $this->engine()->applyConsequence($run, RunConsequence::Wound, $run->leader, times: 2);

        $this->assertSame(2, $run->leader?->refresh()->wounds);

        $adjustment = TrackerAdjustment::query()
            ->where('subject_id', $run->run_leader_character_id)
            ->where('tracker', Tracker::Wounds)
            ->sole();

        $this->assertSame(0, $adjustment->value_before);
        $this->assertSame(2, $adjustment->value_after);
        $this->assertSame(2, $adjustment->delta);
    }

    public function test_alerts_raise_the_strength_of_everything_left(): void
    {
        $run = $this->started();

        $this->engine()->applyConsequence($run, RunConsequence::Alert, times: 3);
        $run = $run->refresh();

        $this->assertSame(3, $run->alerts);
        $this->assertSame(3, $run->alertsAvailable());

        $this->engine()->activate($run);
        $this->dice->willRoll(2, 1)->willRoll(4, 8);

        // Printed 2, plus 2 for standing at three Alerts.
        $outcome = $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);

        $this->assertSame(2, $outcome->strength->fromAlerts);
        $this->assertSame(4, $outcome->strength->total());
    }

    /**
     * Alerts are temporary Credits as well as strength, so spending them makes
     * the rest of the Facility easier. That tension is the point.
     */
    public function test_spending_alerts_as_credits_lowers_the_strength_they_were_adding(): void
    {
        $run = $this->started(physical: 0, cyber: 2);
        $this->budget($run, 0);
        $this->engine()->applyConsequence($run, RunConsequence::Alert, times: 3);

        // The first cyber card is free; passing it makes the second cost 1.
        $this->engine()->activate($run->refresh());
        $this->walkPast($run->refresh());

        // Paid out of the Alert pool, since the budget is empty.
        $this->engine()->activate($run->refresh(), alertsToSpend: 1);

        $run = $run->refresh();
        $this->assertSame(1, $run->alerts_spent);
        $this->assertSame(2, $run->alertsAvailable());
        $this->assertTrue($this->engine()->cursor($run)->cardIsActive());

        $this->dice->willRoll(2, 1)->willRoll(3, 8);
        $outcome = $this->engine()->challenge($run, RunnerSkill::Hack, 2);

        // Two Alerts standing is +1, where three would have been +2.
        $this->assertSame(1, $outcome->strength->fromAlerts);
    }

    public function test_security_may_spend_alerts_to_trigger_an_effect(): void
    {
        $run = $this->started(['body' => 9]);
        $this->engine()->applyConsequence($run, RunConsequence::Alert, times: 6);

        $this->engine()->triggerWithAlerts($run->refresh(), RunConsequence::Wound, $run->leader);

        $run = $run->refresh();
        $this->assertSame(5, $run->alerts_spent);
        $this->assertSame(1, $run->alertsAvailable());
        $this->assertSame(1, $run->leader?->refresh()->wounds);
    }

    public function test_alerts_cannot_buy_more_alerts(): void
    {
        $run = $this->started();
        $this->engine()->applyConsequence($run, RunConsequence::Alert, times: 20);

        $this->expectException(ValidationException::class);

        $this->engine()->triggerWithAlerts($run->refresh(), RunConsequence::Alert);
    }

    public function test_an_effect_security_cannot_afford_is_refused(): void
    {
        $run = $this->started();

        $this->expectException(ValidationException::class);

        $this->engine()->triggerWithAlerts($run, RunConsequence::EndTheRun);
    }

    public function test_a_retry_faces_the_same_card_again(): void
    {
        $run = $this->started(physical: 2);
        $this->engine()->activate($run);

        $this->dice->will([1, 1])->will([8, 8]);
        $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);
        $this->engine()->applyConsequence($run->refresh(), RunConsequence::Retry);

        $this->assertTrue($run->refresh()->retry_pending);

        $card = $this->engine()->cursor($run->refresh())->card;
        $run = $this->engine()->advance($run->refresh());

        $cursor = $this->engine()->cursor($run);

        $this->assertSame(0, $run->cards_passed);
        $this->assertFalse($run->retry_pending);
        $this->assertSame($card?->id, $cursor->card?->id);
        // A new pass against the same card, and the card is still Active.
        $this->assertSame(2, $cursor->pass);
        $this->assertSame(RunStep::Activate, $cursor->step);
        $this->assertSame(RunEvent::TYPE_RETRIED, $run->events->last()?->type);
    }

    public function test_end_the_run_finishes_it_there_and_then(): void
    {
        $run = $this->started(physical: 3);
        $this->engine()->activate($run);

        $this->dice->will([1, 1])->will([8, 8]);
        $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);
        $this->engine()->applyConsequence($run->refresh(), RunConsequence::EndTheRun);

        $run = $run->refresh();

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertNotNull($run->ended_at);
        $this->assertSame(RunEvent::TYPE_FAILED, $run->events->last()?->type);
    }

    /**
     * "For each 'End the Run' that you have ignored (including this one), you
     * take 1 Wound, 1 Tag and 1 Alert" - so the count is the multiplier.
     */
    public function test_ignoring_end_the_run_costs_one_of_each_per_ignore(): void
    {
        $run = $this->started(['body' => 9]);
        $leader = $run->leader;

        $this->engine()->ignoreEndTheRun($run, $leader);

        $run = $run->refresh();
        $this->assertSame(1, $run->ignored_end_the_run);
        $this->assertSame(1, $run->alerts);
        $this->assertSame(1, $leader?->refresh()->wounds);
        $this->assertSame(1, $leader?->refresh()->tags);
        $this->assertTrue($run->retry_pending);

        $this->engine()->ignoreEndTheRun($run, $leader);

        $run = $run->refresh();
        $this->assertSame(2, $run->ignored_end_the_run);
        // 1 for the first, 2 for the second.
        $this->assertSame(3, $run->alerts);
        $this->assertSame(3, $leader?->refresh()->wounds);
        $this->assertSame(3, $leader?->refresh()->tags);
    }

    public function test_ignoring_end_the_run_can_take_the_runner_out(): void
    {
        $run = $this->started(['body' => 1]);
        $leader = $run->leader;

        $this->engine()->ignoreEndTheRun($run, $leader);

        $run = $run->refresh();

        $this->assertSame(RunDeparture::Incapacitated, $run->participants->first()?->left_reason);
        $this->assertSame(RunStatus::Failed, $run->status);
    }

    // ------------------------------------------------------------------
    // Breather (3.4.2)
    // ------------------------------------------------------------------

    /**
     * "Leaving a Run when the rest of the group continue may have an effect on
     * your gang's Notoriety" - *may*, so nothing moves here and the log says it
     * is Control's call.
     */
    public function test_leaving_moves_no_notoriety(): void
    {
        [$turn, $facility] = $this->facility();
        $leader = $this->runner($turn->game_id);
        $mate = $this->runner($turn->game_id);

        $run = $this->engine()->submit($turn, $facility, $leader, [$mate->id]);
        $run = $this->engine()->begin($run);

        $this->engine()->leave($run, $mate);

        $run = $run->refresh();
        $this->assertCount(1, $run->activeParticipants());
        $this->assertSame(RunDeparture::Left, $run->participants->firstWhere('character_id', $mate->id)?->left_reason);
        $this->assertSame(
            0,
            TrackerAdjustment::query()->where('tracker', Tracker::Notoriety)->count(),
        );
        $this->assertTrue(RunDeparture::Left->mayCostNotoriety());
    }

    public function test_a_leader_who_leaves_hands_over_to_a_named_successor(): void
    {
        [$turn, $facility] = $this->facility();
        $leader = $this->runner($turn->game_id);
        $second = $this->runner($turn->game_id);
        $third = $this->runner($turn->game_id);

        $run = $this->engine()->submit($turn, $facility, $leader, [$second->id, $third->id]);
        $run = $this->engine()->begin($run);

        $run = $this->engine()->leave($run, $leader, newLeader: $third);

        $this->assertSame($third->id, $run->run_leader_character_id);
        $this->assertSame(RunEvent::TYPE_LEADER_CHANGED, $run->events->last()?->type);
        $this->assertFalse($run->events->last()?->payload['chosen_at_random']);
    }

    /**
     * "If one cannot be chosen democratically, then it should be chosen
     * randomly" - rolled through the same dice everything else uses, so a test
     * can say who got it.
     */
    public function test_a_leader_who_leaves_without_a_successor_hands_over_at_random(): void
    {
        [$turn, $facility] = $this->facility();
        $leader = $this->runner($turn->game_id);
        $second = $this->runner($turn->game_id);
        $third = $this->runner($turn->game_id);

        $run = $this->engine()->submit($turn, $facility, $leader, [$second->id, $third->id]);
        $run = $this->engine()->begin($run);

        // Two left in, and the die says the second of them.
        $this->dice->will([2]);

        $run = $this->engine()->leave($run, $leader);

        $this->assertSame($third->id, $run->run_leader_character_id);
        $this->assertTrue($run->events->last()?->payload['chosen_at_random']);
    }

    public function test_the_last_runner_leaving_fails_the_run(): void
    {
        $run = $this->started();

        $run = $this->engine()->leave($run, $run->leader);

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertNull($run->run_leader_character_id);
    }

    public function test_wounds_reaching_body_carries_a_runner_out(): void
    {
        [$turn, $facility] = $this->facility();
        $leader = $this->runner($turn->game_id, ['body' => 2]);
        $mate = $this->runner($turn->game_id);

        $run = $this->engine()->submit($turn, $facility, $leader, [$mate->id]);
        $run = $this->engine()->begin($run);

        $this->dice->will([1]);
        $this->engine()->applyConsequence($run, RunConsequence::Wound, $leader, times: 2);

        $run = $run->refresh();

        $this->assertSame(
            RunDeparture::Incapacitated,
            $run->participants->firstWhere('character_id', $leader->id)?->left_reason,
        );
        // The run goes on, under a new Leader.
        $this->assertSame(RunStatus::Running, $run->status);
        $this->assertSame($mate->id, $run->run_leader_character_id);
        $this->assertStringContainsString(
            'permanent Equipment',
            (string) $run->events->firstWhere('type', RunEvent::TYPE_INCAPACITATED)?->description,
        );
    }

    // ------------------------------------------------------------------
    // Finishing (3.4.1, 3.4.4, 3.4.5)
    // ------------------------------------------------------------------

    public function test_breaking_the_last_card_succeeds_the_run(): void
    {
        $run = $this->started(physical: 1);
        $this->engine()->activate($run);

        $this->dice->will([8, 8])->will([1, 1]);
        $outcome = $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);
        $this->assertTrue($outcome->runnersWon);

        $run = $this->engine()->advance($run->refresh());

        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertSame(1, $run->cards_passed);
        $this->assertNotNull($run->ended_at);
        $this->assertStringContainsString('3.4.3', (string) $run->events->last()?->description);
    }

    /**
     * The Runners meet every physical card before the first cyber one (3.4).
     */
    public function test_the_physical_stack_is_met_before_the_cyber_one(): void
    {
        $run = $this->started(physical: 2, cyber: 1);
        $run->facility->stateForTurn($run->turn)->forceFill(['security_directed' => true])->save();

        $kinds = [];

        for ($card = 0; $card < 3; $card++) {
            $cursor = $this->engine()->cursor($run->refresh());
            $kinds[] = $cursor->card?->kind;
            $this->engine()->activate($run->refresh(), activating: false);
            $this->engine()->advance($run->refresh());
        }

        $this->assertSame(
            [ProtectionKind::Physical, ProtectionKind::Physical, ProtectionKind::Cyber],
            $kinds,
        );
        $this->assertSame(RunStatus::Succeeded, $run->refresh()->status);
    }

    /**
     * 1 Credit for every 3 cards, rounding up, to whoever was last on the run
     * (3.4.4).
     */
    public function test_a_failed_run_pays_the_consolation_to_the_last_runner(): void
    {
        $run = $this->started(physical: 5);
        $run->facility->stateForTurn($run->turn)->forceFill(['security_directed' => true])->save();

        // Four cards walked past, then the Runners give up.
        for ($card = 0; $card < 4; $card++) {
            $this->engine()->activate($run->refresh(), activating: false);
            $this->engine()->advance($run->refresh());
        }

        $leader = $run->leader;
        $run = $this->engine()->leave($run->refresh(), $leader);

        $this->assertSame(RunStatus::Failed, $run->status);
        // Four cards is 2 Credits.
        $this->assertSame(2, $leader?->refresh()->credits);

        $adjustment = TrackerAdjustment::query()
            ->where('tracker', Tracker::CharacterCredits)
            ->sole();

        $this->assertSame(2, $adjustment->delta);
        $this->assertStringContainsString('4 cards passed', (string) $adjustment->reason);
    }

    public function test_a_run_that_got_nowhere_is_paid_nothing(): void
    {
        $run = $this->started();
        $leader = $run->leader;

        $this->engine()->leave($run, $leader);

        $this->assertSame(0, $leader?->refresh()->credits);
        $this->assertSame(0, TrackerAdjustment::query()->where('tracker', Tracker::CharacterCredits)->count());
    }

    /**
     * "If the end of phase is called and you have not yet been successful then
     * your run is treated as unsuccessful" (3.4.5).
     */
    public function test_the_action_phase_ending_fails_every_unfinished_run(): void
    {
        [$turn, $facility] = $this->facility();
        $running = $this->engine()->begin(
            $this->engine()->submit($turn, $facility, $this->runner($turn->game_id))
        );
        $submitted = $this->engine()->submit($turn, $facility, $this->runner($turn->game_id));

        $closed = $this->engine()->failUnfinishedRuns($turn);

        $this->assertSame(2, $closed);
        $this->assertSame(RunStatus::Failed, $running->refresh()->status);
        $this->assertSame(RunStatus::Failed, $submitted->refresh()->status);
        $this->assertStringContainsString(
            'the Action phase ended',
            (string) $running->refresh()->events->last()?->description,
        );
    }

    public function test_a_finished_run_is_left_alone(): void
    {
        $run = Run::factory()->succeeded()->create();

        $this->assertSame(0, $this->engine()->failUnfinishedRuns($run->turn));
        $this->assertSame(RunStatus::Succeeded, $run->refresh()->status);
    }

    /**
     * A Charge needs Directing, and what the extra consequences *are* is the
     * sentence the card prints - so this records the payment and leaves the
     * words to whoever is running the card.
     */
    public function test_a_charge_is_paid_from_the_budget_and_recorded(): void
    {
        $run = $this->started(physical: 1, chargeCost: 2);
        $this->budget($run, 4);
        $run->facility->stateForTurn($run->turn)->forceFill(['security_directed' => true])->save();

        $this->engine()->activate($run);
        $this->dice->will([1, 1])->will([8, 8]);
        $this->engine()->challenge($run->refresh(), RunnerSkill::Brawn, 2);

        $event = $this->engine()->charge($run->refresh());

        $this->assertSame(RunEvent::TYPE_CHARGED, $event->type);
        $this->assertSame(2, $run->facility->stateForTurn($run->turn)->refresh()->security_budget_spent);
        $this->assertStringContainsString('One Tag.', $event->description);
    }

    public function test_a_card_with_no_charge_cannot_be_charged(): void
    {
        $run = $this->started();
        $run->facility->stateForTurn($run->turn)->forceFill(['security_directed' => true])->save();

        $this->expectException(ValidationException::class);

        $this->engine()->charge($run);
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * A game with a turn and a Facility holding the given stacks.
     *
     * @return array{0: Turn, 1: Facility}
     */
    private function facility(int $physical = 1, int $cyber = 0, ?int $chargeCost = null): array
    {
        $game = Game::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $game->id]);
        $facility = Facility::factory()->create(['game_id' => $game->id]);

        foreach ([[ProtectionKind::Physical, $physical], [ProtectionKind::Cyber, $cyber]] as [$kind, $count]) {
            for ($position = 1; $position <= $count; $position++) {
                $type = ProtectionCardType::factory()->ofKind($kind);

                if ($chargeCost !== null) {
                    $type = $type->withCharge($chargeCost);
                }

                FacilityProtectionCard::factory()->create([
                    'facility_id' => $facility->id,
                    'protection_card_type_id' => $type->create(['game_id' => $game->id])->id,
                    'kind' => $kind,
                    'position' => $position,
                ]);
            }
        }

        return [$turn, $facility];
    }

    /**
     * A solo run already under way.
     *
     * @param  array<string, mixed>  $leaderAttributes
     */
    private function started(
        array $leaderAttributes = [],
        int $physical = 1,
        int $cyber = 0,
        ?int $chargeCost = null,
        int $tags = 0,
    ): Run {
        [$turn, $facility] = $this->facility($physical, $cyber, $chargeCost);

        // Skills of 2 by default, so a solo Leader rolls exactly 2 dice and a
        // test can queue the faces without arithmetic.
        $leader = $this->runner(
            $turn->game_id,
            $leaderAttributes + ['tags' => $tags, 'brawn' => 2, 'hack' => 2],
        );

        return $this->engine()->begin(
            $this->engine()->submit($turn, $facility, $leader)
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function runner(int $gameId, array $attributes = []): Character
    {
        return Character::factory()->create($attributes + ['game_id' => $gameId]);
    }

    /**
     * Put Credits on the Facility for the turn.
     *
     * Written straight onto the turn state rather than through
     * FacilityDefenceService, because what these tests are about is what the
     * run spends rather than how the escrow got there - and the escrow itself
     * is already covered by the Facility defence tests.
     */
    private function budget(Run $run, int $credits): void
    {
        $run->facility->stateForTurn($run->turn)
            ->forceFill(['security_budget' => $credits])
            ->save();
    }

    /**
     * Break the card in front of a solo Runner and move on.
     *
     * Wins the check rather than taking a consequence, because every
     * consequence changes something a test may be measuring - an Alert would
     * quietly make the next card harder. The card is given a printed strength
     * of 0, so the only dice Security gets are the ones the run's own state
     * hands it, and {@see ChallengeStrength} is asked how many that is rather
     * than the arithmetic being written out twice.
     */
    private function walkPast(Run $run, RunnerSkill $skill = RunnerSkill::Brawn): void
    {
        $cursor = $this->engine()->cursor($run);

        if ($cursor->cardIsActive()) {
            $strength = ChallengeStrength::for(
                printed: 0,
                cardsPassed: $run->active_cards_passed,
                alerts: $run->alertsAvailable(),
                boosts: $cursor->activation?->boosts ?? 0,
            );

            $this->dice
                ->willRoll((int) $run->leader?->getAttribute($skill->column()), 8)
                ->willRoll($strength->total(), 1);

            $this->engine()->challenge($run, $skill, 0);
        }

        $this->engine()->advance($run->refresh());
    }
}
