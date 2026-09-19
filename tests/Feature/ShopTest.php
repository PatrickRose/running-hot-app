<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\ShopListingStatus;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Models\Gang;
use App\Models\ProtectionCardType;
use App\Models\ShopListing;
use App\Models\ShopPurchase;
use App\Models\TrackerAdjustment;
use App\Models\User;
use App\Services\FacilityDefenceService;
use App\Services\ShopService;
use App\Services\TurnEngine;
use App\Support\ShopPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop as the players use it (rulebook 3.3.3, and 2.1 for the market).
 *
 * The rulebook gives the shop very little and this holds onto all of it: Control
 * announces what is for sale, it goes first come first served, and
 * research-only cards never reach it. Everything else here is about the two
 * counters being genuinely different transactions - a Protection Card out of
 * the Corporation's Credits into the Corporation's hand, an Equipment card out
 * of a Runner's own pocket into their own.
 */
class ShopTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    private Gang $gang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

        $this->corporation = Corporation::factory()->for($this->game)->create([
            'name' => 'Gordon',
            'credits' => 40,
        ]);

        $this->gang = Gang::factory()->for($this->game)->create(['name' => 'Facers']);
    }

    /**
     * The Protection counter: the Corporation pays, the Corporation holds it,
     * and the shelf goes down by one.
     */
    public function test_security_buys_a_protection_card_out_of_the_corporations_credits(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PS013');
        $listing = $this->listing($card, price: 12, stock: 3);

        $this->actingAs($security->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(28, $this->corporation->fresh()->credits);
        $this->assertSame(1, app(FacilityDefenceService::class)->copiesInHand($this->corporation, $card));
        $this->assertSame(2, $listing->fresh()->stock);

        // The Credits go through the ledger like every other number the game
        // argues about, with the Security player's name on them.
        $adjustment = TrackerAdjustment::query()
            ->where('tracker', Tracker::CorporationCredits)
            ->latest('id')
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(-12, $adjustment->delta);
        $this->assertSame($security->user->id, $adjustment->actor_id);
        $this->assertStringContainsString('Angel', (string) $adjustment->reason);
    }

    /**
     * And the sale is on record, with both people on it: who stood at the
     * counter, and whose Credits paid.
     */
    public function test_a_sale_records_the_buyer_and_the_purse(): void
    {
        $security = $this->security();
        $listing = $this->listing($this->protectionCard('PS009'), price: 5);

        $this->actingAs($security->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertSessionHasNoErrors();

        /** @var ShopPurchase $purchase */
        $purchase = ShopPurchase::query()->sole();

        $this->assertSame($security->id, $purchase->character_id);
        $this->assertSame($this->corporation->id, $purchase->corporation_id);
        $this->assertSame(5, $purchase->price_paid);
        $this->assertSame($this->game->currentPhase()?->id, $purchase->phase_id);
    }

    /**
     * The market: a Runner pays out of their own pocket into their own hand.
     */
    public function test_a_runner_buys_equipment_out_of_their_own_credits(): void
    {
        $runner = $this->runner('Wicker', credits: 20);
        $card = $this->equipmentCard('ESP003');
        $listing = $this->equipmentListing($card, price: 7, stock: 2);

        $this->actingAs($runner->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $runner->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(13, $runner->fresh()->credits);
        $this->assertSame(1, $runner->equipmentCopiesOf($card->id));
        $this->assertSame(1, $listing->fresh()->stock);
        // Nothing came off the Corporation: a Runner's purse is their own.
        $this->assertSame(40, $this->corporation->fresh()->credits);
    }

    /**
     * 3.3.3 hands the Protection Card list to the Security player, which is the
     * same boundary the Facility board draws - the CEO reads it and does not
     * buy from it.
     */
    public function test_a_ceo_may_not_buy_a_protection_card(): void
    {
        $ceo = $this->seat('Ada Bellweather', CharacterRole::Ceo);
        $listing = $this->listing($this->protectionCard('PS013'));

        $this->actingAs($ceo->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $ceo->id])
            ->assertForbidden();

        $this->assertSame(40, $this->corporation->fresh()->credits);
        $this->assertSame(0, ShopPurchase::query()->count());
    }

    public function test_a_runner_may_not_buy_a_protection_card(): void
    {
        $runner = $this->runner('Wicker');
        $listing = $this->listing($this->protectionCard('PS013'));

        $this->actingAs($runner->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $runner->id])
            ->assertForbidden();
    }

    public function test_a_security_player_may_not_buy_equipment(): void
    {
        $security = $this->security();
        $listing = $this->equipmentListing($this->equipmentCard('EEP002'));

        $this->actingAs($security->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertForbidden();
    }

    /**
     * A player may hold several characters, so the seat standing at the counter
     * is named - and it has to be one of theirs.
     */
    public function test_a_player_cannot_spend_somebody_elses_seat(): void
    {
        $mine = $this->runner('Wicker', credits: 50);
        $theirs = $this->runner('Con', credits: 50);
        $listing = $this->equipmentListing($this->equipmentCard('ESP003'), price: 5);

        $this->actingAs($mine->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $theirs->id])
            ->assertForbidden();

        $this->assertSame(50, $theirs->fresh()->credits);
    }

    /**
     * "The Corporation shop will be open during the Setup Phase" (3.3.3), and
     * 2.1 puts the market there too.
     */
    public function test_the_shop_is_shut_outside_the_setup_phase(): void
    {
        $security = $this->security();
        $listing = $this->listing($this->protectionCard('PS013'));

        $this->advancePastSetup();

        $this->actingAs($security->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertForbidden();

        $this->assertSame(40, $this->corporation->fresh()->credits);
    }

    /**
     * Control is never out of time: a player phones a purchase in, and it
     * cannot wait for the clock to come round again.
     */
    public function test_control_may_buy_when_the_shop_is_shut(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PS013');
        $listing = $this->listing($card, price: 5);

        $this->advancePastSetup();

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/shop/{$listing->id}/buy", [
                'character_id' => $security->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, app(FacilityDefenceService::class)->copiesInHand($this->corporation, $card));
    }

    /**
     * Control reaches the players' own route too, which is what
     * ShopListingPolicy::before() is for - and it is the only way a seat
     * somebody else holds can be bought with.
     */
    public function test_control_may_buy_through_the_players_route(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PS013');
        $listing = $this->listing($card, price: 5);

        $this->actingAs($this->control())
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, app(FacilityDefenceService::class)->copiesInHand($this->corporation, $card));
    }

    /**
     * A rumoured card is on the list precisely so Security can plan around it -
     * "hold your Credits, the Angel lands next turn" - and cannot be bought.
     */
    public function test_a_rumoured_line_cannot_be_bought_from(): void
    {
        $security = $this->security();
        $listing = $this->listing($this->protectionCard('PS013'), status: ShopListingStatus::Rumoured);

        $this->actingAs($security->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertSessionHasErrors('listing');

        $this->assertSame(40, $this->corporation->fresh()->credits);
    }

    /**
     * Not even for Control: a rumoured line is one Control has not announced
     * yet, and announcing it is a click away.
     */
    public function test_control_is_refused_a_rumoured_line_too(): void
    {
        $security = $this->security();
        $listing = $this->listing($this->protectionCard('PS013'), status: ShopListingStatus::Rumoured);

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/shop/{$listing->id}/buy", [
                'character_id' => $security->id,
            ])
            ->assertSessionHasErrors('listing');
    }

    /**
     * First come first served (3.3.3): the second Corporation to reach for the
     * last Angel does not get one.
     */
    public function test_a_sold_out_line_refuses_the_next_buyer(): void
    {
        $security = $this->security();
        $listing = $this->listing($this->protectionCard('PS013'), price: 1, stock: 1);

        $this->actingAs($security->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $listing->fresh()->stock);

        $this->actingAs($security->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertSessionHasErrors('listing');

        $this->assertSame(1, ShopPurchase::query()->count());
    }

    /**
     * A line with no stock figure is Control saying the shop has as many of
     * these as anybody wants, which is an ordinary thing to say about a basic
     * card.
     */
    public function test_a_line_with_no_stock_figure_never_runs_out(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PS003');
        $listing = $this->listing($card, price: 1, stock: null);

        foreach (range(1, 4) as $ignored) {
            $this->actingAs($security->user)
                ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
                ->assertSessionHasNoErrors();
        }

        $this->assertNull($listing->fresh()->stock);
        $this->assertSame(4, app(FacilityDefenceService::class)->copiesInHand($this->corporation, $card));
    }

    /**
     * Credits are deliberately unbounded so Control can record a debt, so
     * nothing clamps an overspend - it has to be refused outright, and nothing
     * may move on the way.
     */
    public function test_a_purse_that_will_not_cover_it_is_refused_whole(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PS013');
        $listing = $this->listing($card, price: 100, stock: 2);

        $this->actingAs($security->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertSessionHasErrors('credits');

        $this->assertSame(40, $this->corporation->fresh()->credits);
        $this->assertSame(0, app(FacilityDefenceService::class)->copiesInHand($this->corporation, $card));
        $this->assertSame(2, $listing->fresh()->stock);
        $this->assertSame(0, ShopPurchase::query()->count());
    }

    public function test_a_runner_who_cannot_afford_it_is_refused(): void
    {
        $runner = $this->runner('Wicker', credits: 3);
        $card = $this->equipmentCard('EEP002');
        $listing = $this->equipmentListing($card, price: 9);

        $this->actingAs($runner->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $runner->id])
            ->assertSessionHasErrors('credits');

        $this->assertSame(3, $runner->fresh()->credits);
        $this->assertSame(0, $runner->equipmentCopiesOf($card->id));
    }

    /**
     * Nought is a real price - a card Control is handing out - and it must not
     * be mistaken for an unaffordable one.
     */
    public function test_a_free_card_can_be_taken_with_an_empty_purse(): void
    {
        $runner = $this->runner('Ghost', credits: 0);
        $card = $this->equipmentCard('ESP001');
        $listing = $this->equipmentListing($card, price: 0);

        $this->actingAs($runner->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $runner->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $runner->equipmentCopiesOf($card->id));
        $this->assertSame(0, $runner->fresh()->credits);
    }

    /**
     * Even for Control, and even through the service: Equipment belongs to the
     * side that runs, and a Katana in a CEO's hand is a row nothing reads.
     */
    public function test_equipment_cannot_be_sold_to_a_corporate_seat_at_all(): void
    {
        $ceo = $this->seat('Ada Bellweather', CharacterRole::Ceo);
        $listing = $this->equipmentListing($this->equipmentCard('EEP002'), price: 0);

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/shop/{$listing->id}/buy", [
                'character_id' => $ceo->id,
            ])
            ->assertSessionHasErrors('character_id');
    }

    /**
     * The counter you are shown is the one 3.3.3 hands you. A Runner is not
     * given the Protection Card list, which would be a catalogue of what they
     * are about to meet - reconnaissance the rulebook makes them pay for.
     */
    public function test_a_runner_is_shown_the_market_and_not_the_protection_list(): void
    {
        $runner = $this->runner('Wicker');
        $this->listing($this->protectionCard('PS013'));
        $this->equipmentListing($this->equipmentCard('ESP003'));

        $shop = app(ShopPresenter::class)->forPlayer($this->game, $runner->user);

        $this->assertNull($shop['protection']);
        $this->assertNotNull($shop['equipment']);
        $this->assertSame(['Shiv'], array_column(array_column($shop['equipment']['listings'], 'card'), 'name'));
        $this->assertSame([$runner->id], array_column($shop['equipment']['buyers'], 'character_id'));
    }

    /**
     * A Corporate seat that is not Security reads the list and is offered
     * nobody to buy with - which is what makes the page honest about who does
     * the buying.
     */
    public function test_a_ceo_reads_the_protection_list_with_no_buyer(): void
    {
        $ceo = $this->seat('Ada Bellweather', CharacterRole::Ceo);
        $this->listing($this->protectionCard('PS013'));

        $shop = app(ShopPresenter::class)->forPlayer($this->game, $ceo->user);

        $this->assertNotNull($shop['protection']);
        $this->assertCount(1, $shop['protection']['listings']);
        $this->assertSame([], $shop['protection']['buyers']);
        $this->assertNull($shop['equipment']);
    }

    /**
     * The purse on screen is the one that will be checked, so a Security player
     * is shown their Corporation's Credits rather than their own.
     */
    public function test_a_security_buyer_is_quoted_the_corporations_purse(): void
    {
        $security = $this->security();
        $security->forceFill(['credits' => 2])->save();
        $card = $this->protectionCard('PS013');
        $listing = $this->listing($card);

        app(FacilityDefenceService::class)->setCopiesInHand($this->corporation, $card, 2);

        $shop = app(ShopPresenter::class)->forPlayer($this->game, $security->user);
        $buyer = $shop['protection']['buyers'][0];

        // The buyer is the person and the purse is the Corporation, and the
        // two are different strings - the seat picker names who is standing at
        // the counter.
        $this->assertSame('Gordon Security', $buyer['name']);
        $this->assertSame('Gordon', $buyer['purse_name']);
        $this->assertSame(40, $buyer['credits']);
        $this->assertSame(2, $buyer['held'][$listing->id]);
    }

    /**
     * Withdrawn is off the players' list entirely: Control has taken it down,
     * and a line saying "you cannot have this and never will" is noise on a
     * page whose job is to be shopped from.
     */
    public function test_a_withdrawn_line_is_off_the_players_list(): void
    {
        $security = $this->security();
        $this->listing($this->protectionCard('PS013'), status: ShopListingStatus::Withdrawn);
        $this->listing($this->protectionCard('PS009'));

        $shop = app(ShopPresenter::class)->forPlayer($this->game, $security->user);

        $this->assertSame(['Orc'], array_column(array_column($shop['protection']['listings'], 'card'), 'name'));
    }

    /**
     * A rumoured line is not: it is the whole reason 3.3.3's list has three
     * categories rather than one.
     */
    public function test_a_rumoured_line_stays_on_the_players_list(): void
    {
        $security = $this->security();
        $this->listing($this->protectionCard('PS013'), status: ShopListingStatus::Rumoured);

        $shop = app(ShopPresenter::class)->forPlayer($this->game, $security->user);
        $listing = $shop['protection']['listings'][0];

        $this->assertSame('rumoured', $listing['status']);
        $this->assertFalse($listing['available']);
    }

    /**
     * Any card can be put out, a research-only one included.
     *
     * 3.3.3 says those "will not be available for general sale", and this
     * application does not enforce it: the shop is how Control hands a card
     * over at a price, and a card the tree was meant to unlock is exactly the
     * sort of thing that gets sold once because the table went somewhere
     * interesting. It sells like any other line.
     */
    public function test_a_research_only_card_can_be_stocked_and_bought(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PR010');
        $listing = $this->listing($card, price: 9, stock: 1);

        $this->actingAs($security->user)
            ->post("/shop/{$listing->id}/buy", ['character_id' => $security->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(31, $this->corporation->fresh()->credits);
        $this->assertSame(1, app(FacilityDefenceService::class)->copiesInHand($this->corporation, $card));
    }

    public function test_the_page_renders_for_a_player(): void
    {
        $runner = $this->runner('Wicker');
        $this->equipmentListing($this->equipmentCard('ESP003'));

        $this->actingAs($runner->user)->get('/shop')->assertOk();
    }

    /**
     * Somebody with no seat at all - Press, or a player between characters -
     * gets a page with neither counter rather than an error.
     */
    public function test_a_player_with_no_seat_sees_neither_counter(): void
    {
        $user = User::factory()->create();

        $shop = app(ShopPresenter::class)->forPlayer($this->game, $user);

        $this->assertNull($shop['protection']);
        $this->assertNull($shop['equipment']);
    }

    /**
     * Move the clock off Setup, which is the only phase the shop is open in.
     */
    private function advancePastSetup(): void
    {
        $phase = $this->game->currentPhase();

        $this->assertNotNull($phase);

        app(TurnEngine::class)->advance($phase);
        $this->game->refresh();
    }

    private function control(): User
    {
        return User::factory()->control()->create();
    }

    /**
     * A claimed seat: the character, with its user reachable as ->user.
     */
    private function seat(string $name, CharacterRole $role, int $credits = 0): Character
    {
        $user = User::factory()->create();

        /** @var Character $character */
        $character = Character::factory()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $role->isCorporate() ? $this->corporation->id : null,
            'gang_id' => $role->isCorporate() ? null : $this->gang->id,
            'name' => $name,
            'role' => $role,
            'credits' => $credits,
        ]);

        $character->forceFill(['user_id' => $user->id])->save();

        return $character->fresh()->load('user');
    }

    private function security(): Character
    {
        return $this->seat('Gordon Security', CharacterRole::Security);
    }

    private function runner(string $name, int $credits = 30): Character
    {
        return $this->seat($name, CharacterRole::Runner, $credits);
    }

    /**
     * A card out of the game's own catalogue, by the code printed on it.
     *
     * By code rather than by name, and out of the seeded list rather than made
     * fresh, because both matter here. A card is identified by its code
     * (Doppleganger is two cards), and a test that makes its own Angel is a
     * test running against a game holding two of them - which reads fine until
     * something asserts on a list of names.
     */
    private function protectionCard(string $code): ProtectionCardType
    {
        /** @var ProtectionCardType */
        return $this->game->protectionCardTypes()->where('code', $code)->sole();
    }

    private function equipmentCard(string $code): EquipmentCardType
    {
        /** @var EquipmentCardType */
        return $this->game->equipmentCardTypes()->where('code', $code)->sole();
    }

    private function listing(
        ProtectionCardType $card,
        int $price = 5,
        ?int $stock = 3,
        ShopListingStatus $status = ShopListingStatus::OnSale,
    ): ShopListing {
        return app(ShopService::class)->stock($this->game, $card, $price, $stock, $status);
    }

    private function equipmentListing(
        EquipmentCardType $card,
        int $price = 5,
        ?int $stock = 3,
        ShopListingStatus $status = ShopListingStatus::OnSale,
    ): ShopListing {
        return app(ShopService::class)->stock($this->game, $card, $price, $stock, $status);
    }
}
