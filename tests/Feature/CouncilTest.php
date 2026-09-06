<?php

namespace Tests\Feature;

use App\Enums\AgendaCardStatus;
use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\AgendaCard;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\CouncilAgendaItem;
use App\Models\Game;
use App\Models\User;
use App\Services\CouncilService;
use App\Services\TurnEngine;
use App\Support\CouncilPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Council as players reach it (rulebook 3.1).
 *
 * The boundary is what these hold onto: a CEO votes for their own Corporation
 * and nobody else's, the Chair's powers belong to whichever Corporation holds
 * the Chair this turn, and a vote the Chair declared secret is withheld from
 * the other players while the Chair still sees every ballot.
 */
class CouncilTest extends TestCase
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
            'council_chair_order' => 1,
        ]);

        $this->dtc = Corporation::factory()->for($this->game)->create([
            'name' => 'DTC',
            'political_will' => 8,
            'council_chair_order' => 2,
        ]);

        // Starting the game opens turn 1's sitting, which is what anchors the
        // recess clock to the Setup phase.
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();
    }

    public function test_starting_a_turn_opens_the_sitting_and_sets_the_recess(): void
    {
        $session = $this->game->currentTurn()->councilSession()->first();

        $this->assertNotNull($session);
        $this->assertTrue($this->gordon->is($session->chair));
        $this->assertNotNull($session->recess_at);

        // Five minutes into Setup by default (3.1.1), give or take the second
        // the test spent getting here.
        $this->assertEqualsWithDelta(
            $this->game->council_recess_seconds,
            $session->recessSecondsRemaining(),
            2,
        );
    }

    public function test_the_council_page_shows_the_agenda_to_every_player(): void
    {
        $this->tabledItem();

        $runner = $this->player(CharacterRole::Runner, null);

        $this->actingAs($runner)
            ->get(route('council'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('council')
                ->where('council.items.0.card.title', 'Water levy')
                // A Runner is not at the Council: no vote, and no sight of the
                // Chair's hand.
                ->where('council.viewer.can_vote', false)
                ->where('council.hand', [])
            );
    }

    public function test_a_ceo_votes_for_their_own_corporation(): void
    {
        $item = $this->tabledItem();
        $ceo = $this->player(CharacterRole::Ceo, $this->gordon);

        $resolutions = $item->card->votableResolutions();

        $this->actingAs($ceo)
            ->post(route('council.ballots.store', ['item' => $item->id]), [
                'allocations' => [
                    $resolutions[0]->id => 5,
                    $resolutions[1]->id => 2,
                ],
            ])
            ->assertRedirect();

        $ballot = $item->liveBallots()->sole();

        $this->assertSame($this->gordon->id, $ballot->corporation_id);
        $this->assertSame(7, $ballot->weight());

        // Weight, not price: the tracker has not moved.
        $this->assertSame(8, $this->gordon->fresh()->political_will);
    }

    public function test_a_player_with_no_ceo_seat_cannot_vote(): void
    {
        $item = $this->tabledItem();
        $security = $this->player(CharacterRole::Security, $this->gordon);

        $this->actingAs($security)
            ->post(route('council.ballots.store', ['item' => $item->id]), [
                'allocations' => [$item->card->votableResolutions()[0]->id => 1],
            ])
            ->assertForbidden();
    }

    public function test_a_secret_vote_is_withheld_from_the_players_and_not_from_the_chair(): void
    {
        $item = $this->tabledItem();

        $chair = $this->player(CharacterRole::Ceo, $this->gordon);
        $rival = $this->player(CharacterRole::Ceo, $this->dtc);

        app(CouncilService::class)->declareSecret($item, true);

        $resolutions = $item->card->votableResolutions();

        $this->actingAs($rival)
            ->post(route('council.ballots.store', ['item' => $item->id]), [
                'allocations' => [$resolutions[0]->id => 4],
            ])
            ->assertRedirect();

        $presenter = app(CouncilPresenter::class);

        $asRival = $presenter->forPlayer($this->game, $rival)['items'][0];
        $asChair = $presenter->forPlayer($this->game, $chair)['items'][0];

        // The rival can see that DTC voted - you watch somebody hand a slip
        // over - but not what it said.
        $this->assertCount(1, $asRival['submitted']);
        $this->assertNull($asRival['totals']);
        $this->assertNull($asRival['breakdown']);

        // The Chair receives the individual breakdowns either way (3.1.2).
        $this->assertSame(4, $asChair['totals'][$resolutions[0]->id]);
        $this->assertCount(1, $asChair['breakdown']);
    }

    public function test_a_public_vote_is_read_out_once_it_is_resolved(): void
    {
        $item = $this->tabledItem();

        $chair = $this->player(CharacterRole::Ceo, $this->gordon);
        $rival = $this->player(CharacterRole::Ceo, $this->dtc);

        $resolutions = $item->card->votableResolutions();

        app(CouncilService::class)->castBallot($item, $this->dtc, [$resolutions[0]->id => 4]);

        $presenter = app(CouncilPresenter::class);

        // Not while the vote is open: the slips are with the Chair.
        $this->assertNull($presenter->forPlayer($this->game, $rival)['items'][0]['breakdown']);

        $this->actingAs($chair)
            ->post(route('council.items.resolve', ['item' => $item->id]))
            ->assertRedirect();

        $resolved = $presenter->forPlayer($this->game, $rival)['items'][0];

        $this->assertTrue($resolved['resolved']);
        $this->assertCount(1, $resolved['breakdown']);
        $this->assertSame($resolutions[0]->text, $resolved['outcome']['text']);
    }

    public function test_only_the_chairing_corporation_may_chair(): void
    {
        $item = $this->tabledItem();
        $session = $this->game->currentTurn()->councilSession()->first();

        $rival = $this->player(CharacterRole::Ceo, $this->dtc);

        $this->actingAs($rival)
            ->post(route('council.items.secret', ['item' => $item->id]), ['secret' => true])
            ->assertForbidden();

        $this->actingAs($rival)
            ->post(route('council.hand.keep', ['session' => $session->id]), ['kept' => [1]])
            ->assertForbidden();
    }

    public function test_a_custom_agenda_goes_player_control_player_chair(): void
    {
        $session = $this->game->currentTurn()->councilSession()->first();

        $author = $this->player(CharacterRole::Runner, null);
        $character = $this->game->characters()->where('user_id', $author->id)->sole();

        $this->actingAs($author)
            ->post(route('council.agenda-cards.store'), [
                'character_id' => $character->id,
                'title' => 'Ban the drones',
                'resolutions' => ['Ban them', 'Licence them'],
            ])
            ->assertRedirect();

        // The one card a player wrote, as against the game's own deck.
        /** @var AgendaCard $card */
        $card = $this->game->agendaCards()->whereNotNull('submitted_by_character_id')->sole();
        $this->assertSame(AgendaCardStatus::Draft, $card->status);

        $this->actingAs($author)
            ->post(route('council.agenda-cards.to-control', ['card' => $card->id]))
            ->assertRedirect();

        $this->assertSame(AgendaCardStatus::WithControl, $card->fresh()->status);

        // It cannot skip Control and go straight to the Chair.
        $this->actingAs($author)
            ->post(route('council.agenda-cards.to-chair', ['card' => $card->id]))
            ->assertSessionHasErrors('status');

        $control = $this->control();

        $this->actingAs($control)
            ->post(route('control.council.agenda-cards.annotate', [
                'game' => $this->game->id,
                'card' => $card->id,
            ]), ['control_note' => 'Genetic Equity will object.'])
            ->assertRedirect();

        $this->assertSame(AgendaCardStatus::Annotated, $card->fresh()->status);
        $this->assertSame('Genetic Equity will object.', $card->fresh()->control_note);

        $this->actingAs($author)
            ->post(route('council.agenda-cards.to-chair', ['card' => $card->id]))
            ->assertRedirect();

        $this->assertSame(AgendaCardStatus::WithChair, $card->fresh()->status);

        // And the Chair rules on it.
        $chair = $this->player(CharacterRole::Ceo, $this->gordon);

        $this->actingAs($chair)
            ->post(route('council.rulings.store', ['session' => $session->id]), [
                'agenda_card_id' => $card->id,
                'ruling' => 'urgent',
            ])
            ->assertRedirect();

        $this->assertSame(AgendaCardStatus::Tabled, $card->fresh()->status);
    }

    public function test_a_player_cannot_hand_on_somebody_elses_card(): void
    {
        $author = $this->player(CharacterRole::Runner, null);
        $character = $this->game->characters()->where('user_id', $author->id)->sole();

        $card = app(CouncilService::class)->draftCustomCard(
            $character,
            'Ban the drones',
            null,
            ['Ban them', 'Licence them'],
        );

        $stranger = $this->player(CharacterRole::Freelancer, null);

        $this->actingAs($stranger)
            ->post(route('council.agenda-cards.to-control', ['card' => $card->id]))
            ->assertForbidden();
    }

    public function test_control_picks_what_the_council_is_asked_about(): void
    {
        // No cards are made here: a new game already holds the game's own deck.
        $control = $this->control();

        $picked = $this->game->agendaCards()
            ->where('status', AgendaCardStatus::Deck)
            ->orderBy('title')
            ->limit(3)
            ->pluck('id');

        $this->actingAs($control)
            ->post(route('control.council.hand', ['game' => $this->game->id]), [
                'cards' => $picked->all(),
            ])
            ->assertRedirect();

        $inHand = $this->game->agendaCards()
            ->where('status', AgendaCardStatus::InHand)
            ->pluck('id');

        $this->assertSame($picked->sort()->values()->all(), $inHand->sort()->values()->all());
    }

    /**
     * The pile a card sits in between the player handing it over and Control
     * handing it back. Control's alone: the Chair has not been given it yet
     * and might never be, so seeing it would be being handed it early.
     */
    public function test_a_card_with_control_is_visible_to_control_and_not_to_the_chair(): void
    {
        $author = $this->player(CharacterRole::Runner, null);
        $character = $this->game->characters()->where('user_id', $author->id)->sole();

        $council = app(CouncilService::class);
        $council->submitToControl($council->draftCustomCard($character, 'Ban the drones', null, ['Ban them', 'Licence them']));

        $presenter = app(CouncilPresenter::class);
        $chair = $this->player(CharacterRole::Ceo, $this->gordon);

        $this->assertSame(
            ['Ban the drones'],
            array_column($presenter->forPlayer($this->game, $this->control())['with_control'], 'title'),
        );

        $this->assertSame([], $presenter->forPlayer($this->game, $chair)['with_control']);

        // And the author can still see where their own card has got to.
        $this->assertSame(
            'With Control',
            $presenter->forPlayer($this->game, $author)['my_cards'][0]['status_label'],
        );
    }

    public function test_control_is_the_only_one_who_may_write_the_deck(): void
    {
        $ceo = $this->player(CharacterRole::Ceo, $this->gordon);

        $this->actingAs($ceo)
            ->post(route('control.council.agenda-cards.store', ['game' => $this->game->id]), [
                'title' => 'A card of my own',
                'resolutions' => ['Yes', 'No'],
            ])
            ->assertForbidden();
    }

    private function tabledItem(): CouncilAgendaItem
    {
        $session = $this->game->currentTurn()->councilSession()->first();

        $card = AgendaCard::factory()
            ->for($this->game)
            ->withResolutions(['Raise it', 'Leave it'])
            ->create(['title' => 'Water levy']);

        return app(CouncilService::class)->tableCard($session, $card);
    }

    private function player(CharacterRole $role, ?Corporation $corporation): User
    {
        $user = User::factory()->create();

        Character::factory()->for($this->game)->create([
            'user_id' => $user->id,
            'corporation_id' => $corporation?->id,
            'role' => $role,
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
