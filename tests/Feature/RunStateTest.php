<?php

namespace Tests\Feature;

use App\Enums\DiceRoller;
use App\Enums\RunDeparture;
use App\Enums\RunStatus;
use App\Enums\RunStep;
use App\Models\Character;
use App\Models\Facility;
use App\Models\FacilityCardActivation;
use App\Models\FacilityProtectionCard;
use App\Models\Run;
use App\Models\RunDiceRoll;
use App\Models\RunEvent;
use App\Models\RunParticipant;
use App\Models\Turn;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The state a Run is made of (rulebook 3.4).
 *
 * The loop that reads and writes this is not built yet, so what is tested here
 * is the shape: that a group hangs together, that the log is a log, and that
 * the card state which makes going second worse belongs to the turn rather than
 * to any one run.
 */
class RunStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_run_hangs_its_group_together(): void
    {
        $run = Run::factory()->create();

        $first = RunParticipant::factory()->for($run)->create(['position' => 1]);
        $second = RunParticipant::factory()->for($run)->create(['position' => 2]);

        $this->assertSame(
            [$first->id, $second->id],
            $run->refresh()->participants->pluck('id')->all(),
        );
        $this->assertSame($run->game_id, $run->turn->game_id);
        $this->assertSame($run->game_id, $run->facility->game_id);
    }

    /**
     * A Runner is on a run once. The group is fixed when the target is
     * submitted in Secret, so there is no joining halfway through.
     */
    public function test_a_runner_cannot_be_on_the_same_run_twice(): void
    {
        $run = Run::factory()->create();
        $character = Character::factory()->create(['game_id' => $run->game_id]);

        RunParticipant::factory()->for($run)->create(['character_id' => $character->id, 'position' => 1]);

        $this->expectException(QueryException::class);

        RunParticipant::factory()->for($run)->create(['character_id' => $character->id, 'position' => 2]);
    }

    /**
     * Almost every question the loop asks is about who is still in, not about
     * who set out: the size of the dice pool, who may take a consequence, and
     * whether the run has failed for want of anybody left.
     */
    public function test_the_group_shrinks_as_runners_leave(): void
    {
        $run = Run::factory()->running()->create();

        $stayed = RunParticipant::factory()->for($run)->create(['position' => 1]);
        RunParticipant::factory()->for($run)->left()->create(['position' => 2]);
        RunParticipant::factory()->for($run)->incapacitated()->create(['position' => 3]);

        $active = $run->refresh()->activeParticipants();

        $this->assertCount(1, $active);
        $this->assertSame($stayed->id, $active->first()?->id);
        $this->assertTrue($stayed->isActive());
    }

    /**
     * Walking away and being carried out are different things: the first may
     * cost the gang Notoriety and the second explicitly does not (3.4.2).
     */
    public function test_leaving_and_being_incapacitated_are_told_apart(): void
    {
        $run = Run::factory()->running()->create();

        $left = RunParticipant::factory()->for($run)->left()->create(['position' => 1]);
        $out = RunParticipant::factory()->for($run)->incapacitated()->create(['position' => 2]);

        $this->assertSame(RunDeparture::Left, $left->left_reason);
        $this->assertSame(RunDeparture::Incapacitated, $out->left_reason);
        $this->assertTrue($left->left_reason->mayCostNotoriety());
        $this->assertFalse($out->left_reason->mayCostNotoriety());
    }

    /**
     * The log reads in the order things happened, which is the whole of its
     * value when Control is settling an argument mid-game.
     */
    public function test_the_event_log_reads_in_order(): void
    {
        $run = Run::factory()->running()->create();

        $activated = RunEvent::factory()->for($run)->create([
            'pass' => 1,
            'step' => RunStep::Activate,
            'type' => 'card.activated',
            'description' => 'Keypad was activated for 0 Credits.',
        ]);
        $rolled = RunEvent::factory()->for($run)->create([
            'pass' => 1,
            'step' => RunStep::Challenge,
            'type' => 'challenge.rolled',
            'payload' => ['strength' => 3, 'from' => ['base' => 2, 'alerts' => 1]],
            'description' => 'The Runners beat Keypad, 3 successes to 1.',
        ]);

        $this->assertSame(
            [$activated->id, $rolled->id],
            $run->refresh()->events->pluck('id')->all(),
        );
        $this->assertSame(['base' => 2, 'alerts' => 1], $rolled->payload['from']);
    }

    /**
     * Every face is kept, because "why did I lose that check?" is the question
     * this table exists to answer.
     */
    public function test_a_roll_keeps_every_face_it_landed_on(): void
    {
        $run = Run::factory()->running()->create();

        $roll = RunDiceRoll::factory()->for($run)->create([
            'roller' => DiceRoller::Security,
            'pool' => 6,
            'die_faces' => 8,
            'faces' => [1, 2, 2, 3, 4, 4],
            'successes' => 0,
            'reason' => 'Keypad, strength 6',
        ]);

        $this->assertSame([1, 2, 2, 3, 4, 4], $roll->refresh()->faces);
        $this->assertSame('6d8, 5+ — 1,2,2,3,4,4 (0 successes)', $roll->readout());
    }

    /**
     * A Wounded Runner rolls d6s rather than d8s, and the die size is stored
     * rather than inferred: the Wound may have healed by the time anyone reads
     * the roll back.
     */
    public function test_a_roll_records_the_die_it_was_made_with(): void
    {
        $run = Run::factory()->running()->create();

        $roll = RunDiceRoll::factory()->for($run)->create([
            'die_faces' => 6,
            'faces' => [5, 2, 6],
            'successes' => 2,
        ]);

        $this->assertSame(6, $roll->die_faces);
        $this->assertStringContainsString('d6', $roll->readout());
    }

    /**
     * A roll can belong to no step at all: the d8 that breaks a tie in run
     * ordering (3.4.1) happens before any run has begun.
     */
    public function test_a_roll_need_not_belong_to_an_event(): void
    {
        $roll = RunDiceRoll::factory()->create([
            'reason' => 'Tiebreak for run order',
        ]);

        $this->assertNull($roll->run_event_id);
        $this->assertNull($roll->event);
    }

    /**
     * The point of hanging activation off the turn: a card the first group paid
     * to turn on is still Active for the second (3.4.2).
     */
    public function test_activation_belongs_to_the_turn_so_a_later_group_meets_a_warm_card(): void
    {
        $facility = Facility::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $facility->game_id]);
        $card = FacilityProtectionCard::factory()->for($facility)->create();

        $first = Run::factory()->create(['facility_id' => $facility->id, 'turn_id' => $turn->id]);
        $second = Run::factory()->create(['facility_id' => $facility->id, 'turn_id' => $turn->id]);

        FacilityCardActivation::factory()->active()->create([
            'turn_id' => $turn->id,
            'facility_protection_card_id' => $card->id,
        ]);

        // Neither run owns the state; both read the same row off the turn.
        foreach ([$first, $second] as $run) {
            $activation = FacilityCardActivation::query()
                ->where('turn_id', $run->turn_id)
                ->where('facility_protection_card_id', $card->id)
                ->sole();

            $this->assertTrue($activation->isActive());
        }
    }

    /**
     * A card cannot be activated twice in one phase, which is what makes the
     * second attempt a no-op rather than a second charge.
     */
    public function test_a_card_has_one_activation_per_turn(): void
    {
        $facility = Facility::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $facility->game_id]);
        $card = FacilityProtectionCard::factory()->for($facility)->create();

        FacilityCardActivation::factory()->create([
            'turn_id' => $turn->id,
            'facility_protection_card_id' => $card->id,
        ]);

        $this->expectException(QueryException::class);

        FacilityCardActivation::factory()->create([
            'turn_id' => $turn->id,
            'facility_protection_card_id' => $card->id,
        ]);
    }

    /**
     * Boosts are cumulative and priced 1, 2, 3... Credits, and they stay on the
     * card for the rest of the phase — so the next one is priced from what the
     * card already carries rather than from what this run has paid.
     */
    public function test_the_next_boost_is_priced_from_the_boosts_already_on_the_card(): void
    {
        $fresh = FacilityCardActivation::factory()->active()->create();
        $boosted = FacilityCardActivation::factory()->boosted(2)->create();

        $this->assertSame(1, $fresh->nextBoostCost());
        $this->assertSame(3, $boosted->nextBoostCost());
        $this->assertSame(3, $boosted->boost_credits_spent);
    }

    /**
     * A row without a timestamp is a card Security tried and failed to pay for,
     * which leaves it Inactive and skipped. That is not the same as never
     * having reached it, so it is worth a row.
     */
    public function test_an_unpaid_activation_is_recorded_but_inactive(): void
    {
        $attempt = FacilityCardActivation::factory()->create(['activation_cost' => 2]);

        $this->assertFalse($attempt->isActive());
        $this->assertSame(2, $attempt->activation_cost);
    }

    public function test_a_run_knows_whether_it_is_over(): void
    {
        $this->assertFalse(RunStatus::Submitted->isFinished());
        $this->assertFalse(RunStatus::Running->isFinished());
        $this->assertTrue(RunStatus::Succeeded->isFinished());
        $this->assertTrue(RunStatus::Failed->isFinished());
    }

    /**
     * The four steps run in the order 3.4.2 gives them, and the Breather ends a
     * pass rather than leading anywhere within one.
     */
    public function test_the_loop_runs_in_rulebook_order(): void
    {
        $this->assertSame(RunStep::Challenge, RunStep::Activate->next());
        $this->assertSame(RunStep::Consequence, RunStep::Challenge->next());
        $this->assertSame(RunStep::Breather, RunStep::Consequence->next());
        $this->assertNull(RunStep::Breather->next());
    }

    /**
     * A run reaches a Facility through its own game, and the queue at that
     * Facility is ordered within one turn.
     */
    public function test_a_facility_lists_the_runs_against_it(): void
    {
        $facility = Facility::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $facility->game_id]);

        Run::factory()->create([
            'facility_id' => $facility->id,
            'turn_id' => $turn->id,
            'order_index' => 1,
            'order_reason' => 'Fewest people',
        ]);
        Run::factory()->create([
            'facility_id' => $facility->id,
            'turn_id' => $turn->id,
            'order_index' => 2,
            'order_reason' => 'Fewest people',
        ]);

        $this->assertCount(2, $facility->refresh()->runs);
        $this->assertCount(2, $turn->refresh()->runs);
        $this->assertCount(2, $facility->game->refresh()->runs);
    }

    /**
     * Why the tiebreaker is stored rather than recomputed: the last one is a d8
     * roll, so the order is not reproducible from the roster alone.
     */
    public function test_a_run_records_what_put_it_where_it_is(): void
    {
        $run = Run::factory()->create([
            'order_index' => 2,
            'order_reason' => 'Run Leader rolled 3 on a d8',
        ]);

        $this->assertSame(2, $run->order_index);
        $this->assertSame('Run Leader rolled 3 on a d8', $run->order_reason);
    }
}
