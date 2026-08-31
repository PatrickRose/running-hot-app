<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\ProtectionKind;
use App\Enums\Tracker;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\TrackerAdjustment;
use App\Models\User;
use App\Services\FacilityDefenceService;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use App\Support\GamePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security arranging their own Corporation's defences (rulebook 3.3.4).
 *
 * This used to be Control's alone. The rules did not move when it stopped being
 * so - what these tests hold onto is the boundary: a Security player may arrange
 * their own Corporation's Facilities and nobody else's, the Credits still come
 * off through the ledger, and a stack is still Secret from everyone outside the
 * Corporation that owns it (3.4.2).
 */
class SecurityDefendsOwnFacilitiesTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

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

    /**
     * A player sitting in one of a Corporation's seats.
     */
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

    private function security(): User
    {
        return $this->seat($this->corporation, CharacterRole::Security);
    }

    /**
     * A card in the catalogue, with copies in the Corporation's hand.
     */
    private function card(string $name, ProtectionKind $kind, int $copies = 2): ProtectionCardType
    {
        /** @var ProtectionCardType $type */
        $type = ProtectionCardType::factory()->for($this->game)->create([
            'name' => $name,
            'code' => null,
            'kind' => $kind,
        ]);

        app(FacilityDefenceService::class)->setCopiesInHand($this->corporation, $type, $copies);

        return $type;
    }

    private function install(ProtectionCardType $type): FacilityProtectionCard
    {
        return app(FacilityDefenceService::class)->install($this->facility, $type);
    }

    // -- Installing ---------------------------------------------------------

    public function test_security_installs_a_card_from_their_own_hand(): void
    {
        $card = $this->card('Orc', ProtectionKind::Physical, copies: 2);

        $this->actingAs($this->security())
            ->post("/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $installed = $this->facility->protectionCards()->sole();

        $this->assertSame($card->id, $installed->protection_card_type_id);
        // The outermost slot, which is what installing means in 3.3.4.
        $this->assertSame(1, $installed->position);
        // And the copy left the hand rather than being conjured.
        $this->assertSame(1, app(FacilityDefenceService::class)
            ->copiesInHand($this->corporation->refresh(), $card));
    }

    /**
     * The rules did not become suggestions when the route moved off Control.
     */
    public function test_a_card_the_corporation_does_not_hold_is_refused(): void
    {
        $card = $this->card('Angel', ProtectionKind::Physical, copies: 0);

        $this->actingAs($this->security())
            ->post("/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertSessionHasErrors('protection_card_type_id');

        $this->assertSame(0, $this->facility->protectionCards()->count());
    }

    // -- Arranging ----------------------------------------------------------

    /**
     * @return array{0: FacilityProtectionCard, 1: FacilityProtectionCard, 2: FacilityProtectionCard}
     */
    private function installThree(): array
    {
        return [
            $this->install($this->card('Alpha', ProtectionKind::Physical)),
            $this->install($this->card('Bravo', ProtectionKind::Physical)),
            $this->install($this->card('Charlie', ProtectionKind::Physical)),
        ];
    }

    public function test_security_reorders_a_stack_and_is_charged_for_it(): void
    {
        [$alpha, $bravo, $charlie] = $this->installThree();
        $before = $this->corporation->refresh()->credits;

        // Charlie, Bravo, Alpha (the stack is outermost first, so installing in
        // that order leaves Charlie in front). Moving Charlie to the back keeps
        // Bravo and Alpha in their relative order, so only one card moves.
        $this->actingAs($this->security())
            ->post("/facilities/{$this->facility->id}/cards/order", [
                'kind' => ProtectionKind::Physical->value,
                'order' => [$bravo->id, $alpha->id, $charlie->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [$bravo->id, $alpha->id, $charlie->id],
            $this->facility->protectionCards()->orderBy('position')->pluck('id')->all(),
        );
        $this->assertSame($before - 1, $this->corporation->refresh()->credits);
    }

    /**
     * Every Credit a player moves has to be answerable three turns later, which
     * is the whole point of the ledger. A route that charged without writing one
     * would be worse than a route that did not exist.
     */
    public function test_the_charge_lands_in_the_ledger_against_the_player(): void
    {
        [$alpha, $bravo, $charlie] = $this->installThree();
        $security = $this->security();

        $this->actingAs($security)
            ->post("/facilities/{$this->facility->id}/cards/order", [
                'kind' => ProtectionKind::Physical->value,
                'order' => [$bravo->id, $alpha->id, $charlie->id],
            ])
            ->assertSessionHasNoErrors();

        /** @var TrackerAdjustment $entry */
        $entry = TrackerAdjustment::query()
            ->where('tracker', Tracker::CorporationCredits)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(-1, $entry->delta);
        $this->assertSame($security->id, $entry->actor_id);
        $this->assertStringContainsString('Attercliffe Yard', (string) $entry->reason);
    }

    /**
     * The board asks this as the cards move, so it must cost nothing to ask.
     */
    public function test_the_quote_reports_the_cost_without_charging_it(): void
    {
        [$alpha, $bravo, $charlie] = $this->installThree();
        $before = $this->corporation->refresh()->credits;

        $this->actingAs($this->security())
            ->getJson(sprintf(
                '/facilities/%d/cards/order/quote?kind=%s&order[]=%d&order[]=%d&order[]=%d',
                $this->facility->id,
                ProtectionKind::Physical->value,
                $bravo->id,
                $alpha->id,
                $charlie->id,
            ))
            ->assertOk()
            ->assertJson(['moved' => 1, 'discount' => 0, 'cost' => 1, 'affordable' => true]);

        $this->assertSame($before, $this->corporation->refresh()->credits);
        // And it did not quietly rewrite the stack it was asked about.
        $this->assertSame(
            [$charlie->id, $bravo->id, $alpha->id],
            $this->facility->protectionCards()->orderBy('position')->pluck('id')->all(),
        );
    }

    /**
     * A Corporation that cannot pay is told so before it commits, rather than
     * being refused after dragging.
     */
    public function test_the_quote_says_when_a_corporation_cannot_afford_it(): void
    {
        [$alpha, $bravo, $charlie] = $this->installThree();
        $this->corporation->forceFill(['credits' => 0])->save();

        $this->actingAs($this->security())
            ->getJson(sprintf(
                '/facilities/%d/cards/order/quote?kind=%s&order[]=%d&order[]=%d&order[]=%d',
                $this->facility->id,
                ProtectionKind::Physical->value,
                $alpha->id,
                $bravo->id,
                $charlie->id,
            ))
            ->assertOk()
            ->assertJson(['moved' => 2, 'cost' => 2, 'affordable' => false]);
    }

    // -- Removing -----------------------------------------------------------

    public function test_security_removes_a_card_and_the_copy_comes_home(): void
    {
        $card = $this->card('Orc', ProtectionKind::Physical, copies: 2);
        $installed = $this->install($card);

        $this->actingAs($this->security())
            ->delete("/facilities/{$this->facility->id}/cards/{$installed->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($installed->fresh());
        $this->assertSame(2, app(FacilityDefenceService::class)
            ->copiesInHand($this->corporation->refresh(), $card));
    }

    public function test_a_card_in_another_facility_cannot_be_removed_through_this_one(): void
    {
        $other = Facility::factory()
            ->for($this->corporation)
            ->for($this->facility->facilityType)
            ->create(['name' => 'Kelham Island']);

        $installed = $this->install($this->card('Orc', ProtectionKind::Physical));

        $this->actingAs($this->security())
            ->delete("/facilities/{$other->id}/cards/{$installed->id}")
            ->assertNotFound();

        $this->assertNotNull($installed->fresh());
    }

    // -- Who may do any of it -----------------------------------------------

    /**
     * The CEO and the Research player see these stacks - 3.4.2 keeps them Secret
     * from outside the Corporation, not from inside it - and may not move them.
     */
    public function test_another_corporate_seat_reads_the_stacks_but_cannot_move_them(): void
    {
        $card = $this->card('Orc', ProtectionKind::Physical);
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);

        $board = app(GamePresenter::class)->facilityBoard($this->game, $ceo);

        $this->assertNotNull($board['own']);
        $this->assertFalse($board['own']['can_defend']);
        // No hand, because a hand is only useful to somebody who may play from it.
        $this->assertSame([], $board['own']['hand']);

        $this->actingAs($ceo)
            ->post("/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertForbidden();
    }

    public function test_security_at_another_corporation_is_refused(): void
    {
        $rival = Corporation::factory()->for($this->game)->create(['name' => 'ANT']);
        $card = $this->card('Orc', ProtectionKind::Physical);

        $this->actingAs($this->seat($rival, CharacterRole::Security))
            ->post("/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertForbidden();
    }

    public function test_a_runner_is_refused(): void
    {
        $card = $this->card('Orc', ProtectionKind::Physical);
        $runner = User::factory()->create();

        $this->game->characters()->create([
            'name' => 'Some Runner',
            'role' => CharacterRole::Runner,
        ])->forceFill(['user_id' => $runner->id])->save();

        $this->actingAs($runner)
            ->post("/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertForbidden();
    }

    /**
     * Control keeps every power it had. A ruling mid-game must not wait on the
     * Security player being at their laptop.
     */
    public function test_control_may_still_use_these_routes(): void
    {
        $card = $this->card('Orc', ProtectionKind::Physical);

        $this->actingAs(User::factory()->control()->create())
            ->post("/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->facility->protectionCards()->count());
    }

    public function test_a_seat_on_this_games_control_team_may_use_them_too(): void
    {
        $card = $this->card('Orc', ProtectionKind::Physical);
        $user = User::factory()->create();
        ControlMember::factory()->for($this->game)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->facility->protectionCards()->count());
    }

    /**
     * Control of somebody else's game is not Control here. Arranging a stack in
     * a game you are not running is arranging a stranger's defences.
     */
    public function test_a_seat_on_another_games_control_team_is_refused(): void
    {
        $card = $this->card('Orc', ProtectionKind::Physical);
        $user = User::factory()->create();
        ControlMember::factory()->for(Game::factory()->create())->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->facility->protectionCards()->count());
    }

    /**
     * Before the clock starts, Control is still setting the game up. A Security
     * player rearranging a roster mid-setup would be editing somebody else's
     * work.
     */
    public function test_security_cannot_defend_a_game_that_is_not_running(): void
    {
        $card = $this->card('Orc', ProtectionKind::Physical);
        $security = $this->security();

        $this->game->forceFill(['status' => GameStatus::Draft])->save();

        $this->actingAs($security)
            ->post("/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertForbidden();
    }

    // -- What the board hands the page --------------------------------------

    public function test_the_board_gives_security_their_hand(): void
    {
        $this->card('Orc', ProtectionKind::Physical, copies: 3);
        $this->card('Angel', ProtectionKind::Cyber, copies: 0);

        $board = app(GamePresenter::class)
            ->facilityBoard($this->game, $this->security());

        $this->assertTrue($board['own']['can_defend']);

        $hand = collect($board['own']['hand']);

        // Only what is actually in hand: a card down to no copies is not
        // something you are holding.
        $this->assertSame(['Orc'], $hand->pluck('name')->all());
        $this->assertSame(3, $hand->first()['copies_in_hand']);
        // And it carries enough to draw the real card rather than a name.
        $this->assertArrayHasKey('challenge', $hand->first());
        $this->assertArrayHasKey('kind_glyph', $hand->first());
    }
}
