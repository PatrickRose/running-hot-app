<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\ShopListingStatus;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\EquipmentCardType;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\ShopListing;
use App\Models\ShopPurchase;
use App\Models\User;
use App\Services\FacilityDefenceService;
use App\Services\ShopService;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use App\Support\ShopPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The shop as Control runs it (rulebook 3.3.3).
 *
 * "Control will announce the cards available for sale" is the whole of
 * Control's job here, so this is about the list: putting a card on it, pricing
 * it, saying how many there are, moving a rumoured card onto sale, taking a
 * line down, and unwinding a sale that should not have happened.
 */
class ShopControlTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

        $this->corporation = Corporation::factory()->for($this->game)->create([
            'name' => 'Gordon',
            'credits' => 60,
        ]);
    }

    public function test_control_puts_a_card_on_the_list(): void
    {
        $card = $this->protectionCard('PS013');

        $this->actingAs($this->control())
            ->post($this->url(), [
                'family' => 'protection',
                'card_id' => $card->id,
                'price' => 14,
                'stock' => 4,
                'status' => 'on_sale',
                'notes' => 'One per Corporation, please.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        /** @var ShopListing $listing */
        $listing = ShopListing::query()->sole();

        $this->assertSame(14, $listing->price);
        $this->assertSame(4, $listing->stock);
        $this->assertSame(ShopListingStatus::OnSale, $listing->status);
        $this->assertSame('One per Corporation, please.', $listing->notes);
        $this->assertTrue($listing->isProtectionCard());
    }

    /**
     * A blank stock box is the line that never runs out, and it must not be
     * read as nought - which would be a line that has sold out.
     */
    public function test_a_blank_stock_box_is_an_unlimited_line(): void
    {
        $card = $this->equipmentCard('ESP003');

        $this->actingAs($this->control())
            ->post($this->url(), [
                'family' => 'equipment',
                'card_id' => $card->id,
                'price' => 3,
                'stock' => null,
                'status' => 'on_sale',
            ])
            ->assertSessionHasNoErrors();

        $listing = ShopListing::query()->sole();

        $this->assertNull($listing->stock);
        $this->assertFalse($listing->isSoldOut());
        $this->assertTrue($listing->isEquipment());
    }

    /**
     * One line per card: Control changing their mind about a price is an edit,
     * because two prices for the same card is a question nobody can answer at
     * the counter.
     */
    public function test_stocking_the_same_card_twice_edits_the_line(): void
    {
        $card = $this->protectionCard('PS009');

        $control = $this->control();

        $this->actingAs($control)->post($this->url(), [
            'family' => 'protection',
            'card_id' => $card->id,
            'price' => 5,
            'stock' => 2,
            'status' => 'rumoured',
        ])->assertSessionHasNoErrors();

        $this->actingAs($control)->post($this->url(), [
            'family' => 'protection',
            'card_id' => $card->id,
            'price' => 8,
            'stock' => 6,
            'status' => 'on_sale',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ShopListing::query()->count());

        $listing = ShopListing::query()->sole();
        $this->assertSame(8, $listing->price);
        $this->assertSame(6, $listing->stock);
        $this->assertSame(ShopListingStatus::OnSale, $listing->status);
    }

    /**
     * 3.3.3 keeps a research-only card off general sale, and Control's way
     * round it is the card's own availability - which the refusal says.
     */
    public function test_a_research_only_card_is_refused_with_the_way_round_it(): void
    {
        $card = $this->protectionCard('PR010');

        $this->actingAs($this->control())
            ->post($this->url(), [
                'family' => 'protection',
                'card_id' => $card->id,
                'price' => 5,
                'stock' => 1,
                'status' => 'on_sale',
            ])
            ->assertSessionHasErrors('stockable_id');

        $this->assertSame(0, ShopListing::query()->count());
    }

    /**
     * ...and it is not offered in the picker either, because a button that
     * cannot work is worse than no button.
     */
    public function test_a_research_only_card_is_not_offered_to_be_listed(): void
    {
        $shop = app(ShopPresenter::class)->forControl($this->game);
        $names = array_column($shop['unlisted']['protection'], 'name');

        $this->assertContains('Angel', $names);
        $this->assertNotContains('Anzû', $names);
    }

    /**
     * A card already on the list is not offered again, because stocking it
     * would be an edit rather than a new line.
     */
    public function test_a_listed_card_leaves_the_picker(): void
    {
        $card = $this->equipmentCard('EEP002');

        $this->assertContains(
            'Katana',
            array_column(app(ShopPresenter::class)->forControl($this->game)['unlisted']['equipment'], 'name'),
        );

        app(ShopService::class)->stock($this->game, $card, price: 5);

        $this->assertNotContains(
            'Katana',
            array_column(app(ShopPresenter::class)->forControl($this->game)['unlisted']['equipment'], 'name'),
        );
    }

    public function test_control_can_take_an_unsold_line_off_the_list(): void
    {
        $listing = app(ShopService::class)->stock($this->game, $this->protectionCard('PS009'), price: 5);

        $this->actingAs($this->control())
            ->delete("{$this->url()}/{$listing->id}")
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ShopListing::query()->count());
    }

    /**
     * Once a line has been bought from, deleting it would take the sales record
     * with it - and that record is how Control answers "where did that card
     * come from?" three turns later. Withdrawing is what Control wants.
     */
    public function test_a_line_that_has_been_bought_from_cannot_be_deleted(): void
    {
        $security = $this->security();
        $listing = app(ShopService::class)->stock($this->game, $this->protectionCard('PS009'), price: 5);

        app(ShopService::class)->buy($listing, $security);

        $this->actingAs($this->control())
            ->delete("{$this->url()}/{$listing->id}")
            ->assertSessionHasErrors('listing');

        $this->assertSame(1, ShopListing::query()->count());
        $this->assertSame(1, ShopPurchase::query()->count());
    }

    /**
     * Withdrawing keeps the line and its sales, and takes it off the players'
     * list - which Control can still see.
     */
    public function test_withdrawing_keeps_the_line_and_its_sales(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PS009');
        $listing = app(ShopService::class)->stock($this->game, $card, price: 5);

        app(ShopService::class)->buy($listing, $security);

        $this->actingAs($this->control())->post($this->url(), [
            'family' => 'protection',
            'card_id' => $card->id,
            'price' => 5,
            'stock' => 2,
            'status' => 'withdrawn',
        ])->assertSessionHasNoErrors();

        $shop = app(ShopPresenter::class)->forControl($this->game);

        $this->assertCount(1, $shop['listings']);
        $this->assertSame('withdrawn', $shop['listings'][0]['status']);
        $this->assertSame(1, $shop['listings'][0]['sold_count']);
    }

    /**
     * A refund moves all three together: the Credits back, the copy back, the
     * shelf back up. Anything less and the next person to look at the stock
     * will not believe it.
     */
    public function test_a_refund_puts_the_credits_the_copy_and_the_stock_back(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PS013');
        $listing = app(ShopService::class)->stock($this->game, $card, price: 15, stock: 2);

        $purchase = app(ShopService::class)->buy($listing, $security);

        $this->assertSame(45, $this->corporation->fresh()->credits);

        $this->actingAs($this->control())
            ->delete("{$this->url()}/purchases/{$purchase->id}")
            ->assertSessionHasNoErrors();

        $this->assertSame(60, $this->corporation->fresh()->credits);
        $this->assertSame(0, app(FacilityDefenceService::class)->copiesInHand($this->corporation, $card));
        $this->assertSame(2, $listing->fresh()->stock);
        $this->assertSame(0, ShopPurchase::query()->count());
    }

    public function test_a_runners_purchase_refunds_to_their_own_purse(): void
    {
        $runner = $this->runner('Wicker', credits: 25);
        $card = $this->equipmentCard('ESP003');
        $listing = app(ShopService::class)->stock($this->game, $card, price: 6, stock: 1);

        $purchase = app(ShopService::class)->buy($listing, $runner);

        $this->assertSame(19, $runner->fresh()->credits);
        $this->assertSame(0, $listing->fresh()->stock);

        $this->actingAs($this->control())
            ->delete("{$this->url()}/purchases/{$purchase->id}")
            ->assertSessionHasNoErrors();

        $this->assertSame(25, $runner->fresh()->credits);
        $this->assertSame(0, $runner->equipmentCopiesOf($card->id));
        $this->assertSame(1, $listing->fresh()->stock);
    }

    /**
     * A copy already standing in a Facility is not the Corporation's to hand
     * back. Refusing is better than a refund that quietly leaves them a card
     * up - Control takes it off the stack first.
     */
    public function test_a_refund_is_refused_when_the_copy_has_been_installed(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PS013');
        $listing = app(ShopService::class)->stock($this->game, $card, price: 5, stock: 1);

        $purchase = app(ShopService::class)->buy($listing, $security);

        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        $facility = Facility::factory()->for($this->corporation)->for($type)->create();
        app(FacilityDefenceService::class)->install($facility, $card);

        $this->actingAs($this->control())
            ->delete("{$this->url()}/purchases/{$purchase->id}")
            ->assertSessionHasErrors('copies');

        $this->assertSame(1, ShopPurchase::query()->count());
    }

    /**
     * The till roll, which is what makes the stock count reconcilable.
     */
    public function test_the_panel_lists_what_has_been_sold(): void
    {
        $security = $this->security();
        $listing = app(ShopService::class)->stock($this->game, $this->protectionCard('PS013'), price: 9);

        app(ShopService::class)->buy($listing, $security);

        $purchases = app(ShopPresenter::class)->forControl($this->game)['purchases'];

        $this->assertCount(1, $purchases);
        $this->assertSame('Angel', $purchases[0]['card_name']);
        $this->assertSame('Gordon Security', $purchases[0]['buyer_name']);
        $this->assertSame('Gordon', $purchases[0]['corporation_name']);
        $this->assertSame(9, $purchases[0]['price_paid']);
        $this->assertSame('Setup', $purchases[0]['phase']);
    }

    /**
     * A price Control raises next turn must not rewrite what was paid last
     * turn, and the refund has to hand back the number actually taken.
     */
    public function test_a_price_change_does_not_rewrite_what_was_paid(): void
    {
        $security = $this->security();
        $card = $this->protectionCard('PS013');
        $listing = app(ShopService::class)->stock($this->game, $card, price: 10, stock: 5);

        $purchase = app(ShopService::class)->buy($listing, $security);

        app(ShopService::class)->stock($this->game, $card, price: 30, stock: 5);

        $this->assertSame(10, $purchase->fresh()->price_paid);

        app(ShopService::class)->refund($purchase->fresh());

        $this->assertSame(60, $this->corporation->fresh()->credits);
    }

    public function test_the_panel_renders(): void
    {
        app(ShopService::class)->stock($this->game, $this->protectionCard('PS013'), price: 5);

        $this->actingAs($this->control())
            ->get("/control/games/{$this->game->id}/shop")
            ->assertOk();
    }

    public function test_a_player_cannot_reach_the_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get("/control/games/{$this->game->id}/shop")
            ->assertForbidden();
    }

    public function test_a_player_cannot_stock_the_shop(): void
    {
        $card = $this->protectionCard('PS013');

        $this->actingAs(User::factory()->create())
            ->post($this->url(), [
                'family' => 'protection',
                'card_id' => $card->id,
                'price' => 1,
                'status' => 'on_sale',
            ])
            ->assertForbidden();

        $this->assertSame(0, ShopListing::query()->count());
    }

    /**
     * A line in one game is not a line in another, and a card from another
     * game's catalogue cannot be stocked here.
     */
    public function test_a_card_from_another_game_cannot_be_stocked(): void
    {
        $other = Game::factory()->create();
        $card = ProtectionCardType::factory()->for($other)->create(['name' => 'Angel']);

        $this->actingAs($this->control())
            ->post($this->url(), [
                'family' => 'protection',
                'card_id' => $card->id,
                'price' => 5,
                'status' => 'on_sale',
            ])
            ->assertNotFound();
    }

    /**
     * The service guards this too, not just the controller's lookup: it is
     * public API, and a caller that found the card some other way must not be
     * able to list another game's catalogue in this one.
     */
    public function test_the_service_refuses_a_card_from_another_game(): void
    {
        $other = Game::factory()->create();
        /** @var ProtectionCardType $card */
        $card = $other->protectionCardTypes()->where('code', 'PS013')->sole();

        $this->expectException(ValidationException::class);

        app(ShopService::class)->stock($this->game, $card, price: 5);
    }

    private function url(): string
    {
        return "/control/games/{$this->game->id}/shop";
    }

    private function control(): User
    {
        return User::factory()->control()->create();
    }

    private function security(): Character
    {
        /** @var Character $character */
        $character = Character::factory()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $this->corporation->id,
            'name' => 'Gordon Security',
            'role' => CharacterRole::Security,
        ]);

        return $character;
    }

    private function runner(string $name, int $credits): Character
    {
        /** @var Character $character */
        $character = Character::factory()->create([
            'game_id' => $this->game->id,
            'name' => $name,
            'role' => CharacterRole::Runner,
            'credits' => $credits,
        ]);

        return $character;
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
}
