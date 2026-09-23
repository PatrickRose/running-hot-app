<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use App\Services\EquipmentService;
use App\Support\GamePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Control handing Equipment cards out (rulebook 3.4.1).
 *
 * The counterpart of Control setting a Corporation's Protection Card holdings,
 * and set outright for the same reason: the market, a Runner selling to
 * another, a gang splitting a haul and Control handing a card over for a job
 * that went well are all conversations at the table, so what the application
 * records is where a count ended up.
 */
class EquipmentHoldingControlTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Gang $gang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
        $this->gang = Gang::factory()->for($this->game)->create(['name' => 'Facers']);
    }

    public function test_control_can_set_a_count(): void
    {
        $runner = $this->runner('Wicker');
        $card = $this->card();

        $this->actingAs($this->control())
            ->patch($this->url(), [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 4,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $runner->equipmentCopiesOf($card->id));
    }

    /**
     * A card the Runner has no row for at all: buying the first copy off the
     * market is Control writing down a count that did not exist.
     */
    public function test_control_can_give_a_card_the_runner_did_not_carry(): void
    {
        $runner = $this->runner('Ghost');
        $card = $this->card();

        $this->assertSame(0, $runner->equipmentCopiesOf($card->id));

        $this->actingAs($this->control())
            ->patch($this->url(), [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $runner->equipmentCopiesOf($card->id));
    }

    /**
     * Zero is a real answer, and the row stays: a Runner who has spent their
     * last Mini-hospital held one, and a list that forgets it reads as though
     * they never did.
     */
    public function test_setting_a_count_to_zero_keeps_the_row(): void
    {
        $runner = $this->runner('Next');
        $card = $this->card();

        $control = $this->control();

        $this->actingAs($control)->patch($this->url(), [
            'character_id' => $runner->id,
            'equipment_card_type_id' => $card->id,
            'copies' => 2,
        ])->assertSessionHasNoErrors();

        $this->actingAs($control)->patch($this->url(), [
            'character_id' => $runner->id,
            'equipment_card_type_id' => $card->id,
            'copies' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $runner->equipmentCopiesOf($card->id));
        $this->assertSame(
            1,
            $runner->equipmentHoldings()->where('equipment_card_type_id', $card->id)->count(),
        );
    }

    public function test_a_negative_count_is_refused(): void
    {
        $runner = $this->runner('Con');
        $card = $this->card();

        $this->actingAs($this->control())
            ->patch($this->url(), [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $card->id,
                'copies' => -1,
            ])
            ->assertSessionHasErrors('copies');
    }

    /**
     * A Corporate seat may hold Equipment, which it could not before.
     *
     * 2.1 has Runners buying equipment "from other players", so a card reaches
     * the Facility by way of whoever was holding it - and that is as likely to
     * be a CEO who bought it to hand over as a gangmate. Who may hold what is
     * Control's call.
     */
    public function test_a_corporate_character_can_be_given_equipment(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create();

        $ceo = Character::factory()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $corporation->id,
            'name' => 'Ada Bellweather',
            'role' => CharacterRole::Ceo,
        ]);

        $card = $this->card();

        $this->actingAs($this->control())
            ->patch($this->url(), [
                'character_id' => $ceo->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $ceo->equipmentCopiesOf($card->id));
    }

    /**
     * Giving adds to what somebody already has, which is the whole difference
     * between it and setting a count: Control knows what it is handing over
     * and not what is already in the hand.
     */
    public function test_giving_adds_to_the_hand(): void
    {
        $runner = $this->runner('Wicker');
        $card = $this->card();

        app(EquipmentService::class)->setCopiesInHand($runner, $card, 2);

        $this->actingAs($this->control())
            ->post($this->giveUrl(), [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $runner->equipmentCopiesOf($card->id));
    }

    public function test_giving_a_first_copy_opens_a_row(): void
    {
        $runner = $this->runner('Wicker');
        $card = $this->card();

        $this->actingAs($this->control())
            ->post($this->giveUrl(), [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $runner->equipmentCopiesOf($card->id));
    }

    /**
     * Handing somebody no copies of a card is not handing them anything, so it
     * is refused rather than written as a no-op.
     */
    public function test_giving_nothing_is_refused(): void
    {
        $runner = $this->runner('Wicker');
        $card = $this->card();

        $this->actingAs($this->control())
            ->post($this->giveUrl(), [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 0,
            ])
            ->assertSessionHasErrors('copies');
    }

    public function test_only_control_may_give_a_card(): void
    {
        $runner = $this->runner('Wicker');
        $card = $this->card();

        $this->actingAs(User::factory()->create())
            ->post($this->giveUrl(), [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, $runner->equipmentCopiesOf($card->id));
    }

    /**
     * A Freelancer runs with nobody and is still a Runner for 3.4's purposes,
     * so they can be handed a card like anybody else.
     */
    public function test_a_freelancer_can_be_given_equipment(): void
    {
        $freelancer = Character::factory()->create([
            'game_id' => $this->game->id,
            'name' => 'Jack Scanton',
            'role' => CharacterRole::Freelancer,
        ]);

        $card = $this->card();

        $this->actingAs($this->control())
            ->patch($this->url(), [
                'character_id' => $freelancer->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $freelancer->equipmentCopiesOf($card->id));
    }

    /**
     * Both halves are scoped to the game in the URL, so a Control seat on one
     * game cannot reach across to another's Runners or another's catalogue.
     */
    public function test_a_runner_in_another_game_cannot_be_reached(): void
    {
        $other = Game::factory()->create();
        $theirs = Character::factory()->create([
            'game_id' => $other->id,
            'name' => 'Somebody Else',
            'role' => CharacterRole::Runner,
        ]);

        $card = $this->card();

        $this->actingAs($this->control())
            ->patch($this->url(), [
                'character_id' => $theirs->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertNotFound();

        $this->assertSame(0, $theirs->equipmentCopiesOf($card->id));
    }

    public function test_a_card_from_another_games_catalogue_cannot_be_reached(): void
    {
        $runner = $this->runner('Vampire');
        $theirs = EquipmentCardType::factory()->create();

        $this->actingAs($this->control())
            ->patch($this->url(), [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $theirs->id,
                'copies' => 1,
            ])
            ->assertNotFound();
    }

    public function test_a_player_cannot_hand_out_cards(): void
    {
        $runner = $this->runner('Wicker');
        $card = $this->card();

        $this->actingAs(User::factory()->create())
            ->patch($this->url(), [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $card->id,
                'copies' => 5,
            ])
            ->assertForbidden();

        $this->assertSame(0, $runner->equipmentCopiesOf($card->id));
    }

    /**
     * The payload the panel reads: grouped by team, a hand per person, and
     * everybody in no team together at the end rather than banded as one more.
     */
    public function test_the_panel_lists_everybody_grouped_by_team(): void
    {
        $wicker = $this->runner('Wicker');
        $this->runner('Con');

        Character::factory()->create([
            'game_id' => $this->game->id,
            'name' => 'Jack Scanton',
            'role' => CharacterRole::Freelancer,
        ]);

        $corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);
        Character::factory()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $corporation->id,
            'name' => 'Ada Bellweather',
            'role' => CharacterRole::Ceo,
        ]);

        $card = $this->card('Katana');
        app(EquipmentService::class)->setCopiesInHand($wicker, $card, 3);

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game);

        $this->assertCount(3, $groups);

        $this->assertSame('Facers', $groups[0]['name']);
        $this->assertTrue($groups[0]['has_badge']);
        $this->assertSame(['Con', 'Wicker'], array_column($groups[0]['members'], 'name'));

        $this->assertSame('Gordon', $groups[1]['name']);
        $this->assertSame(['Ada Bellweather'], array_column($groups[1]['members'], 'name'));

        $this->assertSame('Unaffiliated', $groups[2]['name']);
        $this->assertFalse($groups[2]['has_badge']);
        $this->assertSame(['Jack Scanton'], array_column($groups[2]['members'], 'name'));

        $hands = array_column($groups[0]['members'], 'cards', 'name');
        $this->assertSame([], $hands['Con']);
        $this->assertSame('Katana', $hands['Wicker'][0]['name']);
        $this->assertSame(3, $hands['Wicker'][0]['copies']);
    }

    /**
     * The picker Control gives a card from offers the whole roster, because a
     * card may be going to whoever is about to pass it on.
     */
    public function test_the_give_picker_offers_the_whole_roster(): void
    {
        $this->runner('Wicker');

        $corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);
        Character::factory()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $corporation->id,
            'name' => 'Ada Bellweather',
            'role' => CharacterRole::Ceo,
        ]);

        $recipients = app(GamePresenter::class)->equipmentRecipients($this->game);

        $this->assertSame(['Ada Bellweather', 'Wicker'], array_column($recipients, 'name'));
        $this->assertSame(['Gordon', 'Facers'], array_column($recipients, 'team'));
    }

    private function url(): string
    {
        return "/control/games/{$this->game->id}/equipment-holdings";
    }

    private function giveUrl(): string
    {
        return "/control/games/{$this->game->id}/equipment-holdings/give";
    }

    private function control(): User
    {
        return User::factory()->control()->create();
    }

    private function runner(string $name): Character
    {
        return Character::factory()->create([
            'game_id' => $this->game->id,
            'gang_id' => $this->gang->id,
            'name' => $name,
            'role' => CharacterRole::Runner,
        ]);
    }

    private function card(string $name = 'Shiv'): EquipmentCardType
    {
        return EquipmentCardType::factory()->for($this->game)->create(['name' => $name]);
    }
}
