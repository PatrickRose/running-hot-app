<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use App\Services\EquipmentService;
use App\Services\TurnEngine;
use App\Support\GamePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One player handing a card to another (rulebook 2.1).
 *
 * "You may buy equipment, either from the market or from other players" is the
 * whole of the rule, and the half these cover is the second one. What travels
 * is the card and nothing else: what came back - Credits, a favour, a share of
 * the next job - is settled at the table, exactly as a research point trade is
 * (3.2.5). A transfer that also took the recipient's Credits would be one
 * player reaching into another's purse on a price only the giver had typed.
 */
class EquipmentTransferTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Gang $gang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->gang = Gang::factory()->for($this->game)->create(['name' => 'Facers']);

        // 2.1 puts buying equipment in the Setup phase, which is where a turn
        // opens.
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();
    }

    public function test_a_player_hands_a_card_to_a_gangmate(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner('Wicker', $user);
        $ghost = $this->runner('Ghost');

        $card = $this->card('Shiv');
        $this->hold($wicker, $card, 3);

        $this->actingAs($user)
            ->post('/equipment/give', [
                'from_character_id' => $wicker->id,
                'to_character_id' => $ghost->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $wicker->equipmentCopiesOf($card->id));
        $this->assertSame(2, $ghost->equipmentCopiesOf($card->id));
    }

    /**
     * Nothing else moves. The exchange is a conversation, so a trade leaves
     * both purses exactly where it found them.
     */
    public function test_no_credits_move_with_the_card(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner('Wicker', $user, ['credits' => 7]);
        $ghost = $this->runner('Ghost', null, ['credits' => 4]);

        $card = $this->card('Katana');
        $this->hold($wicker, $card, 1);

        $this->actingAs($user)
            ->post('/equipment/give', [
                'from_character_id' => $wicker->id,
                'to_character_id' => $ghost->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(7, $wicker->refresh()->credits);
        $this->assertSame(4, $ghost->refresh()->credits);
        $this->assertSame(0, $this->game->trackerAdjustments()->count());
    }

    /**
     * 2.1 names no restriction on the far side of the trade, and who may hold a
     * card is Control's call - so a Runner squaring a debt with a CEO is a
     * trade the rulebook has nothing to say against.
     */
    public function test_a_card_may_be_handed_to_a_corporate_seat(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner('Wicker', $user);

        $corporation = Corporation::factory()->for($this->game)->create();
        $ceo = Character::factory()->for($this->game)->create([
            'corporation_id' => $corporation->id,
            'name' => 'Ada Bellweather',
            'role' => CharacterRole::Ceo,
        ]);

        $card = $this->card();
        $this->hold($wicker, $card, 1);

        $this->actingAs($user)
            ->post('/equipment/give', [
                'from_character_id' => $wicker->id,
                'to_character_id' => $ceo->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $ceo->equipmentCopiesOf($card->id));
    }

    /**
     * The seat has to be yours, because giving spends what is in that hand.
     */
    public function test_a_player_cannot_give_out_of_somebody_elses_hand(): void
    {
        $user = User::factory()->create();
        $this->runner('Wicker', $user);

        $ghost = $this->runner('Ghost');
        $con = $this->runner('Con');

        $card = $this->card();
        $this->hold($ghost, $card, 2);

        $this->actingAs($user)
            ->post('/equipment/give', [
                'from_character_id' => $ghost->id,
                'to_character_id' => $con->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(2, $ghost->equipmentCopiesOf($card->id));
        $this->assertSame(0, $con->equipmentCopiesOf($card->id));
    }

    public function test_more_copies_than_are_in_hand_are_refused(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner('Wicker', $user);
        $ghost = $this->runner('Ghost');

        $card = $this->card('Shiv');
        $this->hold($wicker, $card, 1);

        $this->actingAs($user)
            ->post('/equipment/give', [
                'from_character_id' => $wicker->id,
                'to_character_id' => $ghost->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 2,
            ])
            ->assertSessionHasErrors('copies');

        // Nothing moved on either side, which is what the transaction is for.
        $this->assertSame(1, $wicker->equipmentCopiesOf($card->id));
        $this->assertSame(0, $ghost->equipmentCopiesOf($card->id));
    }

    public function test_handing_a_card_to_yourself_is_refused(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner('Wicker', $user);

        $card = $this->card();
        $this->hold($wicker, $card, 2);

        $this->actingAs($user)
            ->post('/equipment/give', [
                'from_character_id' => $wicker->id,
                'to_character_id' => $wicker->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertSessionHasErrors('to_character_id');

        $this->assertSame(2, $wicker->equipmentCopiesOf($card->id));
    }

    /**
     * 2.1 is the Setup Phase, so a trade during the Action phase is a trade out
     * of time - and being out of time is exactly what Control waves through.
     */
    public function test_a_trade_is_refused_outside_setup(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner('Wicker', $user);
        $ghost = $this->runner('Ghost');

        $card = $this->card();
        $this->hold($wicker, $card, 1);

        $this->advancePastSetup();

        $this->actingAs($user)
            ->post('/equipment/give', [
                'from_character_id' => $wicker->id,
                'to_character_id' => $ghost->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, $ghost->equipmentCopiesOf($card->id));
    }

    public function test_control_may_trade_out_of_time_and_out_of_anybodys_hand(): void
    {
        $wicker = $this->runner('Wicker');
        $ghost = $this->runner('Ghost');

        $card = $this->card();
        $this->hold($wicker, $card, 1);

        $this->advancePastSetup();

        $this->actingAs($this->control())
            ->post('/equipment/give', [
                'from_character_id' => $wicker->id,
                'to_character_id' => $ghost->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $wicker->equipmentCopiesOf($card->id));
        $this->assertSame(1, $ghost->equipmentCopiesOf($card->id));
    }

    /**
     * The page draws the give control off `can_give`, so a hand that is not
     * yours must not carry it - otherwise the button is there to be pressed and
     * the refusal arrives from the server.
     */
    public function test_the_payload_offers_the_give_only_on_your_own_hands(): void
    {
        $user = User::factory()->create();
        $this->runner('Wicker', $user);
        $this->runner('Ghost');

        $mine = app(GamePresenter::class)->equipmentHoldings($this->game, $user);

        $this->assertSame(['Wicker'], array_column($mine[0]['members'], 'name'));
        $this->assertTrue($mine[0]['members'][0]['can_give']);

        // And the Control panel's own view offers it nowhere: Control gives
        // through its own route rather than this one.
        $whole = app(GamePresenter::class)->equipmentHoldings($this->game);

        foreach ($whole[0]['members'] as $member) {
            $this->assertFalse($member['can_give']);
        }
    }

    public function test_the_payload_closes_the_give_outside_setup(): void
    {
        $user = User::factory()->create();
        $this->runner('Wicker', $user);

        $this->advancePastSetup();

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game, $user);

        $this->assertFalse($groups[0]['members'][0]['can_give']);
    }

    /**
     * A row kept at nought rather than deleted, for the reason setCopiesInHand
     * keeps one: somebody who gave their last Mini-hospital away held one, and
     * a list that forgets it reads as though they never did.
     */
    public function test_giving_the_last_copy_keeps_the_row(): void
    {
        $wicker = $this->runner('Wicker');
        $ghost = $this->runner('Ghost');

        $card = $this->card();
        $this->hold($wicker, $card, 1);

        app(EquipmentService::class)->transfer($wicker, $ghost, $card);

        $this->assertSame(0, $wicker->equipmentCopiesOf($card->id));
        $this->assertSame(
            1,
            $wicker->equipmentHoldings()->where('equipment_card_type_id', $card->id)->count(),
        );
    }

    public function test_the_route_needs_a_login(): void
    {
        $this->post('/equipment/give', [])->assertRedirect('/login');
    }

    private function advancePastSetup(): void
    {
        $phase = $this->game->currentPhase();

        $this->assertNotNull($phase);

        app(TurnEngine::class)->advance($phase);
        $this->game->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function runner(string $name, ?User $user = null, array $attributes = []): Character
    {
        return Character::factory()->for($this->game)->create([
            'gang_id' => $this->gang->id,
            'user_id' => $user?->id,
            'name' => $name,
            'role' => CharacterRole::Runner,
            ...$attributes,
        ]);
    }

    private function card(string $name = 'Shiv'): EquipmentCardType
    {
        return EquipmentCardType::factory()->for($this->game)->create(['name' => $name]);
    }

    private function hold(Character $character, EquipmentCardType $card, int $copies): void
    {
        app(EquipmentService::class)->setCopiesInHand($character, $card, $copies);
    }

    private function control(): User
    {
        $user = User::factory()->create();

        ControlMember::factory()->for($this->game)->create(['user_id' => $user->id]);

        return $user;
    }
}
