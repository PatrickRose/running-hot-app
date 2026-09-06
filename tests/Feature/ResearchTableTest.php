<?php

namespace Tests\Feature;

use App\Enums\EquationSide;
use App\Enums\GameStatus;
use App\Enums\PhaseType;
use App\Enums\ResearchCardRestriction;
use App\Enums\ResearchEquationStatus;
use App\Enums\ResearchSuit;
use App\Enums\ResearchZone;
use App\Enums\Tracker;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\ResearchCard;
use App\Models\ResearchEquation;
use App\Models\ResearchSeat;
use App\Models\ResearchSession;
use App\Models\TrackerAdjustment;
use App\Services\ResearchTableService;
use App\Services\TurnEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The research card game (rulebook 3.2.1).
 *
 * What these hold onto is the shape of a turn: an equation spends its cards,
 * the hand and the pool draw back up, the turn passes on, and the points are
 * *not* taken. That last one is the rulebook's own instruction - "Scoring can
 * and should be done while other players are taking their turns" - and it is
 * the reason playing and scoring are two calls rather than one.
 */
class ResearchTableTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $gordon;

    private Corporation $ant;

    protected function setUp(): void
    {
        parent::setUp();

        // A small, flat deck, so a test can say what is in one. The real shapes
        // are Control's to set in config/running_hot.php; the rulebook does not
        // describe either deck.
        config([
            'running_hot.research.private_deck' => [
                'values' => [1, 2, 3], 'copies' => 1, 'wild' => 0,
            ],
            'running_hot.research.public_deck' => [
                'values' => [1, 2, 3], 'copies' => 1, 'wild' => 0,
            ],
        ]);

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->gordon = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);
        $this->ant = Corporation::factory()->for($this->game)->create(['name' => 'Augmented Nucleotech']);
    }

    private function table(): ResearchTableService
    {
        return app(ResearchTableService::class);
    }

    public function test_dealing_seats_every_corporation_and_fills_the_hands_and_the_pool(): void
    {
        $session = $this->table()->openSession($this->game);

        $this->assertSame(2, $session->seats()->count());
        $this->assertSame(1, $session->current_order);

        $this->assertCount(5, $this->table()->hand($this->gordon));
        $this->assertCount(5, $this->table()->hand($this->ant));
        $this->assertCount(6, $this->table()->pool($this->game));

        // Twelve cards each, five of them dealt out.
        $this->assertSame(7, $this->table()->deckCount($this->game, $this->gordon));
        $this->assertSame(6, $this->table()->deckCount($this->game, null));
    }

    public function test_the_action_phase_deals_a_sitting_of_its_own(): void
    {
        $engine = app(TurnEngine::class);
        $phase = $engine->start($this->game);

        // Setup is not the research phase, so nothing is dealt yet.
        $this->assertNull($this->table()->currentSession($this->game->refresh()));

        $engine->advance($phase);

        $session = $this->table()->currentSession($this->game->refresh());

        $this->assertNotNull($session);
        $this->assertSame(PhaseType::Action, $this->game->currentPhase()?->type);
        $this->assertCount(6, $this->table()->pool($this->game));
    }

    public function test_playing_an_equation_spends_its_cards_and_draws_back_up(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);

        [$hand, $pool] = $this->stack($corporation, ResearchSuit::Leaf, 3, ResearchSuit::Maths, 3);

        $equation = $this->table()->play($session, $corporation, [$hand->id], [$pool->id]);

        $this->assertSame(ResearchEquationStatus::Pending, $equation->status);
        $this->assertSame(3, $equation->left_sum);
        $this->assertTrue($equation->balanced);
        $this->assertSame(1, $equation->bonus);

        $this->assertSame(ResearchZone::Spent, $hand->refresh()->zone);
        $this->assertSame(ResearchZone::Spent, $pool->refresh()->zone);

        // Both draw back up: the hand from its own deck, the pool from the
        // public one.
        $this->assertCount(5, $this->table()->hand($corporation));
        $this->assertCount(6, $this->table()->pool($this->game));
    }

    public function test_the_turn_passes_to_the_next_corporation(): void
    {
        $session = $this->table()->openSession($this->game);
        $first = $this->firstToPlay($session);

        [$hand, $pool] = $this->stack($first, ResearchSuit::Leaf, 3, ResearchSuit::Maths, 3);

        $this->table()->play($session, $first, [$hand->id], [$pool->id]);

        $session->refresh();

        $this->assertFalse($session->isTurnOf($first));
        $this->assertSame(2, $session->current_order);
    }

    public function test_a_corporation_cannot_play_out_of_turn(): void
    {
        $session = $this->table()->openSession($this->game);
        $waiting = $this->secondToPlay($session);

        [$hand, $pool] = $this->stack($waiting, ResearchSuit::Leaf, 3, ResearchSuit::Maths, 3);

        $this->expectException(ValidationException::class);

        $this->table()->play($session, $waiting, [$hand->id], [$pool->id]);
    }

    public function test_an_equation_needs_a_card_out_of_your_own_hand(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);

        $pool = $this->table()->pool($this->game);
        $pool[0]->forceFill(['suit' => ResearchSuit::Leaf, 'value' => 3])->save();
        $pool[1]->forceFill(['suit' => ResearchSuit::Maths, 'value' => 3])->save();

        $this->expectException(ValidationException::class);

        $this->table()->play($session, $corporation, [$pool[0]->id], [$pool[1]->id]);
    }

    public function test_another_corporations_hand_is_not_playable(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);
        $rival = $this->secondToPlay($session);

        $mine = $this->table()->hand($corporation)->first();
        $theirs = $this->table()->hand($rival)->first();

        $this->expectException(ValidationException::class);

        $this->table()->play($session, $corporation, [$mine->id], [$theirs->id]);
    }

    public function test_a_card_cannot_be_used_twice_in_one_equation(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);

        $card = $this->table()->hand($corporation)->first();

        $this->expectException(ValidationException::class);

        $this->table()->play($session, $corporation, [$card->id], [$card->id]);
    }

    public function test_a_no_single_card_cannot_be_played_alone(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);

        [$hand, $pool] = $this->stack($corporation, ResearchSuit::Leaf, 3, ResearchSuit::Maths, 3);

        $hand->forceFill(['restriction' => ResearchCardRestriction::NoSingle])->save();

        try {
            $this->table()->play($session, $corporation, [$hand->id], [$pool->id]);
            $this->fail('A No single card should not have been playable alone.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('No single', $exception->getMessage());
        }

        // Refused before anything moved: the cards are still where they were,
        // and it is still this Corporation's turn.
        $this->assertSame(ResearchZone::Hand, $hand->refresh()->zone);
        $this->assertSame(ResearchZone::Pool, $pool->refresh()->zone);
        $this->assertTrue($session->refresh()->isTurnOf($corporation));
        $this->assertSame(0, $session->equations()->count());
    }

    public function test_a_no_single_card_plays_once_it_has_company(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);

        $hand = $this->table()->hand($corporation);
        $pool = $this->table()->pool($this->game);

        $hand[0]->forceFill([
            'suit' => ResearchSuit::Leaf,
            'value' => 3,
            'restriction' => ResearchCardRestriction::NoSingle,
        ])->save();
        $hand[1]->forceFill(['suit' => ResearchSuit::Leaf, 'value' => 1])->save();
        $pool[0]->forceFill(['suit' => ResearchSuit::Maths, 'value' => 2])->save();
        $pool[1]->forceFill(['suit' => ResearchSuit::Maths, 'value' => 2])->save();

        $equation = $this->table()->play(
            $session,
            $corporation,
            [$hand[0]->id, $hand[1]->id],
            [$pool[0]->id, $pool[1]->id],
        );

        $this->assertSame(2, $equation->cards_per_side);
        $this->assertTrue($equation->balanced);

        // The marking is kept in the snapshot, so an equation still reads as
        // the equation that was played once its cards have been gathered back.
        $this->assertSame(
            ResearchCardRestriction::NoSingle->value,
            $equation->left_cards[0]['restriction'],
        );
        $this->assertNull($equation->left_cards[1]['restriction']);
    }

    public function test_a_corporation_whose_deck_runs_dry_leaves_the_table(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);

        // Nothing left to draw with, which is the rulebook's own way out of the
        // game: "You have no cards left in your deck when you try to draw up
        // your hand limit at the end of your turn."
        $corporation->researchCards()->inZone(ResearchZone::Deck)->delete();

        [$hand, $pool] = $this->stack($corporation, ResearchSuit::Leaf, 3, ResearchSuit::Maths, 3);

        $this->table()->play($session, $corporation, [$hand->id], [$pool->id]);

        $seat = $session->seats()->where('corporation_id', $corporation->id)->sole();

        $this->assertFalse($seat->isPlaying());
        $this->assertSame(ResearchSeat::REASON_DECK_EMPTY, $seat->left_reason);
    }

    public function test_scoring_pays_the_chosen_side_and_the_split_bonus_through_the_ledger(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);

        [$hand, $pool] = $this->stack($corporation, ResearchSuit::Leaf, 4, ResearchSuit::Maths, 4);

        $equation = $this->table()->play($session, $corporation, [$hand->id], [$pool->id]);

        $this->table()->score($equation, EquationSide::Left, ResearchSuit::Leaf, [
            ResearchSuit::Maths->value => 1,
        ]);

        $corporation->refresh();

        $this->assertSame(4, $corporation->leaf_points);
        $this->assertSame(1, $corporation->maths_points);

        // Every movement is in the ledger, which is what lets Control answer
        // "why does Gordon have four Leaf?" three turns later.
        $this->assertDatabaseHas('tracker_adjustments', [
            'subject_id' => $corporation->id,
            'tracker' => Tracker::ResearchLeaf->value,
            'delta' => 4,
        ]);
        $this->assertSame(
            2,
            TrackerAdjustment::query()->where('subject_id', $corporation->id)->count(),
        );
    }

    public function test_an_equation_can_only_be_scored_once(): void
    {
        $equation = $this->played();

        $this->table()->score($equation, EquationSide::Left, ResearchSuit::Leaf, [
            ResearchSuit::Leaf->value => 1,
        ]);

        $this->expectException(ValidationException::class);

        $this->table()->score($equation->refresh(), EquationSide::Right, ResearchSuit::Maths, [
            ResearchSuit::Maths->value => 1,
        ]);
    }

    public function test_control_hands_the_points_back_so_a_score_can_be_taken_again(): void
    {
        $equation = $this->played();
        $corporation = $equation->corporation;

        $this->table()->score($equation, EquationSide::Left, ResearchSuit::Leaf, [
            ResearchSuit::Leaf->value => 1,
        ]);

        // Four for the set, plus the balanced bonus of one taken in the same
        // suit.
        $this->assertSame(5, $corporation->refresh()->leaf_points);

        $this->table()->unscore($equation->refresh());

        $this->assertSame(0, $corporation->refresh()->leaf_points);
        $this->assertSame(ResearchEquationStatus::Pending, $equation->refresh()->status);

        // And it can be scored again, differently.
        $this->table()->score($equation, EquationSide::Right, ResearchSuit::Maths, [
            ResearchSuit::Maths->value => 1,
        ]);

        $this->assertSame(0, $corporation->refresh()->leaf_points);
        $this->assertSame(5, $corporation->refresh()->maths_points);
    }

    public function test_voiding_a_scored_equation_takes_its_points_back(): void
    {
        $equation = $this->played();
        $corporation = $equation->corporation;

        $this->table()->score($equation, EquationSide::Left, ResearchSuit::Leaf, [
            ResearchSuit::Leaf->value => 1,
        ]);

        $this->table()->void($equation->refresh(), 'Misdeal');

        $this->assertSame(0, $corporation->refresh()->leaf_points);
        $this->assertSame(ResearchEquationStatus::Voided, $equation->refresh()->status);
        $this->assertSame('Misdeal', $equation->notes);
    }

    public function test_leaving_and_sitting_back_down(): void
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);

        $this->table()->leave($session, $corporation, 'Gone to talk to a Runner');

        $session->refresh();

        $this->assertFalse($session->isTurnOf($corporation));
        $this->assertSame(2, $session->current_order);

        $this->table()->rejoin($session, $corporation);

        $seat = $session->seats()->where('corporation_id', $corporation->id)->sole();

        $this->assertTrue($seat->isPlaying());
        $this->assertNull($seat->left_reason);
    }

    public function test_redrawing_the_turn_order_keeps_one_corporation_per_seat(): void
    {
        $session = $this->table()->openSession($this->game);

        $this->table()->randomiseOrder($session);

        $orders = $session->seats()->orderBy('order')->pluck('order')->all();

        $this->assertSame([1, 2], $orders);
        $this->assertSame(1, $session->refresh()->current_order);
    }

    public function test_re_dealing_gathers_every_card_back_into_its_deck(): void
    {
        $first = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($first);

        [$hand, $pool] = $this->stack($corporation, ResearchSuit::Leaf, 3, ResearchSuit::Maths, 3);
        $this->table()->play($first, $corporation, [$hand->id], [$pool->id]);

        $this->table()->openSession($this->game);

        // Nothing is left spent: the sitting is over and the cards are back.
        $this->assertSame(
            0,
            ResearchCard::query()->where('game_id', $this->game->id)
                ->inZone(ResearchZone::Spent)
                ->count(),
        );
        $this->assertNotNull($first->refresh()->closed_at);
        $this->assertCount(5, $this->table()->hand($corporation));
    }

    /**
     * An equation of 4 Leaf against 4 Maths, played and waiting to be scored.
     */
    private function played(): ResearchEquation
    {
        $session = $this->table()->openSession($this->game);
        $corporation = $this->firstToPlay($session);

        [$hand, $pool] = $this->stack($corporation, ResearchSuit::Leaf, 4, ResearchSuit::Maths, 4);

        return $this->table()->play($session, $corporation, [$hand->id], [$pool->id]);
    }

    /**
     * Rewrite one card in a Corporation's hand and one in the pool, so a test
     * can play a known equation out of a shuffled deal.
     *
     * @return array{0: ResearchCard, 1: ResearchCard}
     */
    private function stack(
        Corporation $corporation,
        ResearchSuit $handSuit,
        int $handValue,
        ResearchSuit $poolSuit,
        int $poolValue,
    ): array {
        $hand = $this->table()->hand($corporation)->first();
        $pool = $this->table()->pool($this->game)->first();

        $hand->forceFill(['suit' => $handSuit, 'value' => $handValue])->save();
        $pool->forceFill(['suit' => $poolSuit, 'value' => $poolValue])->save();

        return [$hand, $pool];
    }

    private function firstToPlay(ResearchSession $session): Corporation
    {
        return $session->currentSeat()->corporation;
    }

    private function secondToPlay(ResearchSession $session): Corporation
    {
        return $session->seats()
            ->where('order', '>', $session->current_order)
            ->orderBy('order')
            ->firstOrFail()
            ->corporation;
    }
}
