<?php

namespace Tests\Feature;

use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\User;
use App\Services\FacilityDefenceService;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Owning copies of a Protection Card.
 *
 * The briefings give each Corporation counts, and the count matters because of
 * the one-copy-per-Facility rule of rulebook 3.3.4: four copies of a card can
 * defend four Facilities and no more. Without this the same card could be
 * installed everywhere for nothing.
 */
class ProtectionCardHoldingTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
        $this->corporation = Corporation::factory()->for($this->game)->create(['credits' => 50]);
    }

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    protected function defence(): FacilityDefenceService
    {
        return app(FacilityDefenceService::class);
    }

    protected function facility(string $name = 'Attercliffe Yard'): Facility
    {
        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        return Facility::factory()
            ->for($this->corporation)
            ->for($type)
            ->create(['name' => $name]);
    }

    protected function card(int $copies = 1): ProtectionCardType
    {
        return ProtectionCardType::factory()
            ->for($this->game)
            ->heldBy($this->corporation->id, $copies)
            ->create(['name' => 'Security team']);
    }

    public function test_installing_takes_a_copy_out_of_the_hand(): void
    {
        $card = $this->card(copies: 4);

        $this->defence()->install($this->facility(), $card);

        $this->assertSame(3, $this->defence()->copiesInHand($this->corporation, $card));
    }

    public function test_a_corporation_with_no_copies_left_is_refused(): void
    {
        $card = $this->card(copies: 1);

        $this->defence()->install($this->facility('First'), $card);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has no copies of Security team left to install');

        $this->defence()->install($this->facility('Second'), $card);
    }

    /**
     * A card the Corporation was never given is the same as one it has run out
     * of. Control raises the count first.
     */
    public function test_a_card_the_corporation_never_held_is_refused(): void
    {
        $card = ProtectionCardType::factory()->for($this->game)->create();

        $this->expectException(ValidationException::class);

        $this->defence()->install($this->facility(), $card);
    }

    public function test_a_refused_install_leaves_no_card_behind(): void
    {
        $card = ProtectionCardType::factory()->for($this->game)->create();
        $facility = $this->facility();

        try {
            $this->defence()->install($facility, $card);
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(0, $facility->protectionCards()->count());
    }

    public function test_removing_puts_the_copy_back(): void
    {
        $card = $this->card(copies: 2);

        $installed = $this->defence()->install($this->facility(), $card);
        $this->assertSame(1, $this->defence()->copiesInHand($this->corporation, $card));

        $this->defence()->remove($installed);

        $this->assertSame(2, $this->defence()->copiesInHand($this->corporation, $card));
    }

    /**
     * A card Control installed without the Corporation ever holding one still
     * has to return somewhere.
     */
    public function test_removing_a_card_the_corporation_never_held_still_returns_it(): void
    {
        $card = $this->card(copies: 1);
        $installed = $this->defence()->install($this->facility(), $card);

        // Take the hand down to nothing behind the install's back, as though
        // Control had corrected the count while the card was in the wall.
        $this->defence()->setCopiesInHand($this->corporation, $card, 0);

        $this->defence()->remove($installed);

        $this->assertSame(1, $this->defence()->copiesInHand($this->corporation, $card));
    }

    /**
     * Four copies stretch to four Facilities, because a Facility may hold only
     * one copy of a card (rulebook 3.3.4).
     */
    public function test_copies_cap_how_many_facilities_a_card_can_defend(): void
    {
        $card = $this->card(copies: 2);

        $this->defence()->install($this->facility('First'), $card);
        $this->defence()->install($this->facility('Second'), $card);

        $this->expectException(ValidationException::class);

        $this->defence()->install($this->facility('Third'), $card);
    }

    public function test_control_can_set_a_count(): void
    {
        $card = $this->card(copies: 1);

        $this->actingAs($this->control())
            ->patch("/control/games/{$this->game->id}/protection-card-holdings", [
                'corporation_id' => $this->corporation->id,
                'protection_card_type_id' => $card->id,
                'copies' => 6,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $this->defence()->copiesInHand($this->corporation, $card));
    }

    /**
     * A card the Corporation has no row for at all: buying the first copy from
     * the shop is Control writing down a count that did not exist.
     */
    public function test_control_can_give_a_card_the_corporation_did_not_hold(): void
    {
        $card = ProtectionCardType::factory()->for($this->game)->create();

        $this->actingAs($this->control())
            ->patch("/control/games/{$this->game->id}/protection-card-holdings", [
                'corporation_id' => $this->corporation->id,
                'protection_card_type_id' => $card->id,
                'copies' => 3,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $this->defence()->copiesInHand($this->corporation, $card));
    }

    public function test_a_negative_count_is_refused(): void
    {
        $card = $this->card();

        $this->actingAs($this->control())
            ->patch("/control/games/{$this->game->id}/protection-card-holdings", [
                'corporation_id' => $this->corporation->id,
                'protection_card_type_id' => $card->id,
                'copies' => -1,
            ])
            ->assertSessionHasErrors('copies');
    }

    /**
     * Setting the hand does not disturb what is already installed: the two are
     * separate halves of the same count.
     */
    public function test_setting_a_count_leaves_installed_copies_alone(): void
    {
        $card = $this->card(copies: 2);
        $facility = $this->facility();

        $this->defence()->install($facility, $card);

        $this->defence()->setCopiesInHand($this->corporation, $card, 5);

        $this->assertSame(5, $this->defence()->copiesInHand($this->corporation, $card));
        $this->assertSame(1, $facility->protectionCards()->count());
    }

    public function test_a_player_cannot_set_a_count(): void
    {
        $card = $this->card();

        $this->actingAs(User::factory()->create())
            ->patch("/control/games/{$this->game->id}/protection-card-holdings", [
                'corporation_id' => $this->corporation->id,
                'protection_card_type_id' => $card->id,
                'copies' => 9,
            ])
            ->assertForbidden();
    }

    /**
     * A card belonging to another game cannot be handed to this Corporation, or
     * a Corporation could be given a card its game does not have.
     */
    public function test_a_card_from_another_game_is_refused(): void
    {
        $other = Game::factory()->create();
        $card = ProtectionCardType::factory()->for($other)->create();

        $this->actingAs($this->control())
            ->patch("/control/games/{$this->game->id}/protection-card-holdings", [
                'corporation_id' => $this->corporation->id,
                'protection_card_type_id' => $card->id,
                'copies' => 1,
            ])
            ->assertNotFound();
    }
}
