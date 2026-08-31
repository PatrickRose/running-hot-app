<?php

namespace Tests\Unit;

use App\Enums\AgendaCardStatus;
use App\Enums\CouncilAttendance;
use App\Enums\GameStatus;
use App\Enums\PhaseType;
use App\Enums\ResolutionAmendment;
use App\Enums\Tracker;
use App\Models\AgendaCard;
use App\Models\Corporation;
use App\Models\CouncilAgendaItem;
use App\Models\CouncilSession;
use App\Models\Game;
use App\Models\TrackerAdjustment;
use App\Models\Turn;
use App\Services\CouncilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The Council's rules (rulebook 3.1), away from any route.
 *
 * Everything the rulebook is specific about is pinned here: three drawn and two
 * kept, five items a turn, two to five resolutions on a card, a vote weighted
 * by Political Will and never charged for it, and a tie that goes back to the
 * Chair rather than being settled by a rule.
 */
class CouncilServiceTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Turn $turn;

    private CouncilService $council;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->turn = $this->game->turns()->create(['number' => 1]);
        $this->council = app(CouncilService::class);
    }

    public function test_it_draws_three_and_keeps_two(): void
    {
        $session = $this->council->openSession($this->turn);

        foreach (range(1, 5) as $index) {
            $this->deckCard('Card '.$index);
        }

        $drawn = $this->council->draw($session);

        $this->assertCount(CouncilSession::CARDS_DRAWN, $drawn);
        $this->assertSame(3, $this->game->agendaCards()->where('status', AgendaCardStatus::Drawn)->count());

        $kept = $drawn->take(2);
        $this->council->keep($session, $kept->pluck('id')->all());

        $this->assertSame(2, $this->game->agendaCards()->where('status', AgendaCardStatus::Tabled)->count());
        $this->assertSame(1, $this->game->agendaCards()->where('status', AgendaCardStatus::Discarded)->count());
        $this->assertSame(2, $this->council->tabledCount($session->refresh()));
    }

    public function test_the_chair_cannot_keep_all_three(): void
    {
        $session = $this->council->openSession($this->turn);

        foreach (range(1, 3) as $index) {
            $this->deckCard('Card '.$index);
        }

        $drawn = $this->council->draw($session);

        $this->expectException(ValidationException::class);

        $this->council->keep($session, $drawn->pluck('id')->all());
    }

    public function test_control_cannot_draw_twice_in_a_turn(): void
    {
        $session = $this->council->openSession($this->turn);

        foreach (range(1, 6) as $index) {
            $this->deckCard('Card '.$index);
        }

        $this->council->draw($session);

        $this->expectException(ValidationException::class);

        $this->council->draw($session->refresh());
    }

    public function test_a_turn_takes_no_more_than_five_agenda_items(): void
    {
        $session = $this->council->openSession($this->turn);

        foreach (range(1, CouncilSession::MAXIMUM_ITEMS) as $index) {
            $this->council->tableCard($session, $this->deckCard('Item '.$index));
        }

        $this->assertSame(CouncilSession::MAXIMUM_ITEMS, $this->council->tabledCount($session));

        $this->expectException(ValidationException::class);

        $this->council->tableCard($session, $this->deckCard('One too many'));
    }

    public function test_a_card_needs_two_resolutions_before_it_can_be_voted_on(): void
    {
        $session = $this->council->openSession($this->turn);

        $card = AgendaCard::factory()->for($this->game)->withResolutions(['Only one'])->create();

        $this->expectException(ValidationException::class);

        $this->council->tableCard($session, $card);
    }

    public function test_only_one_item_is_promoted_each_turn(): void
    {
        $session = $this->council->openSession($this->turn);

        $first = $this->deckCard('Held over');
        $second = $this->deckCard('Also held over');

        foreach ([$first, $second] as $card) {
            $card->forceFill(['status' => AgendaCardStatus::Important])->save();
        }

        $this->council->promote($session, $first);

        $this->expectException(ValidationException::class);

        $this->council->promote($session, $second);
    }

    public function test_an_amendment_changes_nothing_until_control_signs_it_off(): void
    {
        $card = $this->deckCard('Water levy', ['Raise it', 'Leave it']);
        $resolution = $card->resolutions->first();

        $this->council->proposeRewording($resolution, 'Raise it by a third');

        $this->assertSame('Raise it', $resolution->fresh()->text);
        $this->assertSame(ResolutionAmendment::Rewording, $resolution->fresh()->pending_amendment);

        $this->council->signOffAmendment($resolution->fresh());

        $this->assertSame('Raise it by a third', $resolution->fresh()->text);
        $this->assertNull($resolution->fresh()->pending_amendment);
    }

    public function test_a_proposed_addition_is_not_votable_until_it_is_signed_off(): void
    {
        $card = $this->deckCard('Water levy', ['Raise it', 'Leave it']);

        $added = $this->council->proposeAddition($card->fresh(['resolutions']), 'Abolish it');

        $this->assertCount(2, $card->fresh(['resolutions'])->votableResolutions());

        $this->council->signOffAmendment($added->fresh());

        $this->assertCount(3, $card->fresh(['resolutions'])->votableResolutions());
    }

    public function test_a_card_cannot_be_amended_past_its_bounds(): void
    {
        $card = $this->deckCard('Five already', ['One', 'Two', 'Three', 'Four', 'Five']);

        try {
            $this->council->proposeAddition($card, 'Six');
            $this->fail('A sixth resolution should be refused.');
        } catch (ValidationException) {
            // The maximum of 3.1.4.
        }

        $twoLeft = $this->deckCard('Two only', ['Yes', 'No']);

        $this->expectException(ValidationException::class);

        $this->council->proposeRemoval($twoLeft->resolutions->first());
    }

    public function test_a_vote_is_weighted_by_political_will_and_never_charged_for_it(): void
    {
        [$session, $item, $card] = $this->tabledItem();

        $corporation = $this->corporation('Gordon', 9);
        $resolutions = $card->votableResolutions();

        $this->council->castBallot($item, $corporation, [
            $resolutions[0]->id => 6,
            $resolutions[1]->id => 3,
        ]);

        $tally = $this->council->tally($item->fresh());

        $this->assertSame(6, $tally['totals'][$resolutions[0]->id]);
        $this->assertSame(3, $tally['totals'][$resolutions[1]->id]);

        // The weight of the vote, not its price: 3.1 never spends it, so the
        // tracker has not moved and the ledger has nothing to explain.
        $this->assertSame(9, $corporation->fresh()->political_will);
        $this->assertSame(0, TrackerAdjustment::query()->where('tracker', Tracker::PoliticalWill)->count());
    }

    public function test_a_corporation_cannot_vote_with_more_than_it_holds(): void
    {
        [, $item, $card] = $this->tabledItem();

        $corporation = $this->corporation('Gordon', 4);
        $resolutions = $card->votableResolutions();

        $this->expectException(ValidationException::class);

        $this->council->castBallot($item, $corporation, [
            $resolutions[0]->id => 3,
            $resolutions[1]->id => 2,
        ]);
    }

    public function test_a_second_ballot_is_refused_until_the_chair_hands_the_first_back(): void
    {
        [, $item, $card] = $this->tabledItem();

        $corporation = $this->corporation('Gordon', 10);
        $resolutions = $card->votableResolutions();

        $ballot = $this->council->castBallot($item, $corporation, [$resolutions[0]->id => 2]);

        try {
            $this->council->castBallot($item->fresh(), $corporation, [$resolutions[1]->id => 2]);
            $this->fail('A Corporation votes once.');
        } catch (ValidationException) {
            // Ask the Chair for the slip back.
        }

        $this->council->returnBallot($ballot, 'Changed their mind.');

        $this->council->castBallot($item->fresh(), $corporation, [$resolutions[1]->id => 2]);

        $tally = $this->council->tally($item->fresh());

        $this->assertSame(0, $tally['totals'][$resolutions[0]->id]);
        $this->assertSame(2, $tally['totals'][$resolutions[1]->id]);
    }

    public function test_declaring_a_vote_secret_hands_back_the_votes_already_in(): void
    {
        [, $item, $card] = $this->tabledItem();

        $corporation = $this->corporation('Gordon', 10);
        $resolutions = $card->votableResolutions();

        $this->council->castBallot($item, $corporation, [$resolutions[0]->id => 4]);

        $returned = $this->council->declareSecret($item->fresh(), true);

        $this->assertSame(1, $returned);
        $this->assertTrue($item->fresh()->secret);
        $this->assertSame(0, $this->council->tally($item->fresh())['totals'][$resolutions[0]->id]);
    }

    public function test_a_secret_vote_cannot_be_made_public_over_votes_already_cast(): void
    {
        [, $item, $card] = $this->tabledItem();

        $this->council->declareSecret($item, true);

        $corporation = $this->corporation('Gordon', 10);
        $this->council->castBallot($item->fresh(), $corporation, [$card->votableResolutions()[0]->id => 4]);

        $this->expectException(ValidationException::class);

        $this->council->declareSecret($item->fresh(), false);
    }

    public function test_the_most_political_will_carries(): void
    {
        [, $item, $card] = $this->tabledItem();

        $resolutions = $card->votableResolutions();

        $this->council->castBallot($item, $this->corporation('Gordon', 10), [$resolutions[0]->id => 7]);
        $this->council->castBallot($item->fresh(), $this->corporation('DTC', 10), [$resolutions[1]->id => 3]);

        $resolved = $this->council->resolve($item->fresh());

        $this->assertSame($resolutions[0]->id, $resolved->outcome_resolution_id);
        $this->assertFalse($resolved->tie_broken);
        $this->assertSame(AgendaCardStatus::Voted, $card->fresh()->status);
    }

    public function test_a_tie_goes_back_to_the_chair(): void
    {
        [, $item, $card] = $this->tabledItem();

        $resolutions = $card->votableResolutions();

        $this->council->castBallot($item, $this->corporation('Gordon', 10), [$resolutions[0]->id => 5]);
        $this->council->castBallot($item->fresh(), $this->corporation('DTC', 10), [$resolutions[1]->id => 5]);

        try {
            $this->council->resolve($item->fresh());
            $this->fail('A tied vote is the Chair\'s to break.');
        } catch (ValidationException) {
            // 3.1.2: ties are resolved by the Chair.
        }

        $resolved = $this->council->resolve($item->fresh(), $resolutions[1]);

        $this->assertSame($resolutions[1]->id, $resolved->outcome_resolution_id);
        $this->assertTrue($resolved->tie_broken);
    }

    public function test_the_chair_breaks_a_tie_only_between_the_resolutions_that_tied(): void
    {
        [, $item, $card] = $this->tabledItem(['Raise it', 'Leave it', 'Abolish it']);

        $resolutions = $card->votableResolutions();

        $this->council->castBallot($item, $this->corporation('Gordon', 10), [$resolutions[0]->id => 5]);
        $this->council->castBallot($item->fresh(), $this->corporation('DTC', 10), [$resolutions[1]->id => 5]);

        $this->expectException(ValidationException::class);

        $this->council->resolve($item->fresh(), $resolutions[2]);
    }

    public function test_an_absence_costs_political_will_only_when_control_applies_it(): void
    {
        $session = $this->council->openSession($this->turn);
        $corporation = $this->corporation('Gordon', 6);

        $this->council->markAttendance($session, $corporation, PhaseType::Action, CouncilAttendance::Absent);

        // Marking the seat alone moves nothing.
        $this->assertSame(6, $corporation->fresh()->political_will);

        $this->council->applyAttendancePenalty($session, $corporation, PhaseType::Action, 2);

        $this->assertSame(4, $corporation->fresh()->political_will);

        $adjustment = TrackerAdjustment::query()->where('tracker', Tracker::PoliticalWill)->sole();
        $this->assertSame(-2, $adjustment->delta);
        $this->assertStringContainsString('Council', (string) $adjustment->reason);

        // And it is charged once.
        $this->expectException(ValidationException::class);
        $this->council->applyAttendancePenalty($session, $corporation, PhaseType::Action, 2);
    }

    public function test_a_present_seat_cannot_be_charged_for(): void
    {
        $session = $this->council->openSession($this->turn);
        $corporation = $this->corporation('Gordon', 6);

        $this->council->markAttendance($session, $corporation, PhaseType::Setup, CouncilAttendance::Present);

        $this->expectException(ValidationException::class);

        $this->council->applyAttendancePenalty($session, $corporation, PhaseType::Setup, 1);
    }

    public function test_the_chair_rotates_through_the_order_the_game_holds(): void
    {
        $second = $this->corporation('DTC', 5);
        $first = $this->corporation('Gordon', 5);

        $first->forceFill(['council_chair_order' => 1])->save();
        $second->forceFill(['council_chair_order' => 2])->save();

        $this->assertTrue($this->council->nextChair($this->turn)->is($first));

        $turnTwo = $this->game->turns()->create(['number' => 2]);
        $this->assertTrue($this->council->nextChair($turnTwo)->is($second));

        $turnThree = $this->game->turns()->create(['number' => 3]);
        $this->assertTrue($this->council->nextChair($turnThree)->is($first));
    }

    /**
     * @param  array<int, string>|null  $resolutions
     */
    private function deckCard(string $title, ?array $resolutions = null): AgendaCard
    {
        return AgendaCard::factory()
            ->for($this->game)
            ->withResolutions($resolutions)
            ->create(['title' => $title]);
    }

    private function corporation(string $name, int $politicalWill): Corporation
    {
        return Corporation::factory()->for($this->game)->create([
            'name' => $name,
            'political_will' => $politicalWill,
        ]);
    }

    /**
     * A card up for vote this turn, with the session and the card beside it.
     *
     * @param  array<int, string>|null  $resolutions
     * @return array{0: CouncilSession, 1: CouncilAgendaItem, 2: AgendaCard}
     */
    private function tabledItem(?array $resolutions = null): array
    {
        $session = $this->council->openSession($this->turn);
        $card = $this->deckCard('Water levy', $resolutions ?? ['Raise it', 'Leave it']);
        $item = $this->council->tableCard($session, $card);

        return [$session, $item, $card->fresh(['resolutions'])];
    }
}
