<?php

namespace Tests\Feature;

use App\Actions\SeedEquipmentCards;
use App\Actions\SeedProtectionCards;
use App\Actions\SeedTechnologies;
use App\Enums\EquipmentCategory;
use App\Models\Game;
use App\Models\User;
use App\Support\EquipmentCardBlueprint;
use App\Support\ProtectionCardBlueprint;
use App\Support\TechnologyBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three card lists a game is played out of: the Protection Cards Security
 * installs (rulebook 3.3.2), the Equipment Runners carry (3.4.1), and the
 * technologies on the tech trees (3.2.2).
 *
 * All three come with the game rather than with the roster, because none of them
 * depends on it. Control edits them from there.
 */
class CardCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    public function test_a_new_game_gets_all_three_card_lists(): void
    {
        $game = Game::factory()->create();

        $this->assertSame(
            count(ProtectionCardBlueprint::defaults()),
            $game->protectionCardTypes()->count(),
        );
        $this->assertSame(
            count(EquipmentCardBlueprint::defaults()),
            $game->equipmentCardTypes()->count(),
        );
        $this->assertSame(
            count(TechnologyBlueprint::defaults()),
            $game->technologyTypes()->count(),
        );
    }

    /**
     * Every card carries the code printed on it, which is what identifies it and
     * how its artwork is found.
     */
    public function test_every_seeded_card_has_a_code(): void
    {
        $game = Game::factory()->create();

        $this->assertSame(0, $game->protectionCardTypes()->whereNull('code')->count());
        $this->assertSame(0, $game->equipmentCardTypes()->whereNull('code')->count());
        $this->assertSame(0, $game->technologyTypes()->whereNull('code')->count());
    }

    /**
     * Doppleganger is PX011 in the physical stack and PX012 in the cyber one:
     * two different cards that happen to share a title.
     */
    public function test_two_protection_cards_share_the_doppleganger_title(): void
    {
        $game = Game::factory()->create();

        $dopplegangers = $game->protectionCardTypes()
            ->where('name', 'Doppleganger')
            ->orderBy('code')
            ->get();

        $this->assertSame(['PX011', 'PX012'], $dopplegangers->pluck('code')->all());
        $this->assertSame(['physical', 'cyber'], $dopplegangers->pluck('kind.value')->all());
    }

    /**
     * The challenge is the sentence the card prints, which is often not a skill
     * and a number at all.
     */
    public function test_a_challenge_can_be_more_than_a_skill_and_a_number(): void
    {
        $game = Game::factory()->create();

        $shutter = $game->protectionCardTypes()->where('code', 'PS006')->sole();
        $keypad = $game->protectionCardTypes()->where('code', 'PS005')->sole();
        $aosSi = $game->protectionCardTypes()->where('code', 'PR030')->sole();

        $this->assertSame('Brute or Hack (Number of alerts+2)', $shutter->challenge);
        $this->assertSame('Brute/Hack (2)', $keypad->challenge);
        $this->assertStringContainsString('Hack (4+N)', $aosSi->challenge);
    }

    /**
     * Research-unlocked cards are the target of some technology's "Unlock:"
     * effect, so Security cannot buy them until a Research player has got there.
     */
    public function test_research_unlocked_cards_are_not_on_sale_at_the_start(): void
    {
        $game = Game::factory()->create();

        $keresh = $game->protectionCardTypes()->where('code', 'PR005')->sole();
        $orc = $game->protectionCardTypes()->where('code', 'PS009')->sole();

        $this->assertSame('research_only', $keresh->availability->value);
        $this->assertSame('available', $orc->availability->value);
    }

    public function test_a_card_nobody_can_buy_has_no_price_rather_than_a_free_one(): void
    {
        $game = Game::factory()->create();

        // Anzû is unlocked by research and never priced on the card list.
        $this->assertNull($game->protectionCardTypes()->where('code', 'PR010')->sole()->cost);
        // Orc is a shop card.
        $this->assertSame(4, $game->protectionCardTypes()->where('code', 'PS009')->sole()->cost);
    }

    public function test_equipment_carries_its_category(): void
    {
        $game = Game::factory()->create();

        $katana = $game->equipmentCardTypes()->where('code', 'EEP002')->sole();
        $boost = $game->equipmentCardTypes()->where('code', 'ESS006')->sole();
        $firstAid = $game->equipmentCardTypes()->where('code', 'EST005')->sole();

        $this->assertSame(EquipmentCategory::Permanent, $katana->category);
        $this->assertSame(EquipmentCategory::SingleUse, $boost->category);
        $this->assertSame(EquipmentCategory::ThisRun, $firstAid->category);

        // Only Permanent items are equipped before the Run and count against
        // the three-item limit.
        $this->assertTrue($katana->category->isEquippedBeforeTheRun());
        $this->assertFalse($boost->category->isEquippedBeforeTheRun());
        $this->assertTrue($boost->category->returnsToControl());
    }

    /**
     * The bypass cards are granted by a technology rather than sold, so they
     * have no market price.
     */
    public function test_equipment_the_market_does_not_sell_has_no_price(): void
    {
        $game = Game::factory()->create();

        $bypass = $game->equipmentCardTypes()->where('code', 'ERS021')->sole();

        $this->assertNull($bypass->cost);
        $this->assertFalse($bypass->isOnSale());
    }

    public function test_a_technology_is_priced_in_the_four_research_suits(): void
    {
        $game = Game::factory()->create();

        $deerHorns = $game->technologyTypes()->where('code', 'RSR001')->sole();

        $this->assertSame(
            ['cog' => 0, 'brain' => 10, 'leaf' => 8, 'maths' => 0],
            $deerHorns->cost(),
        );
        $this->assertFalse($deerHorns->isFree());
    }

    public function test_a_technology_keeps_its_prerequisites_as_printed_titles(): void
    {
        $game = Game::factory()->create();

        $houndOfHades = $game->technologyTypes()->where('code', 'RMR010')->sole();

        $this->assertSame(
            ['Deadly snake', 'Snake woman', 'Fire dogs'],
            $houndOfHades->prerequisites,
        );
    }

    /**
     * A technology naming a Facility type may only be housed there, so the type
     * is resolved to the game's own catalogue rather than left as a word.
     */
    public function test_a_technology_that_needs_a_facility_type_is_linked_to_it(): void
    {
        $game = Game::factory()->create();

        $mythicalFarm = $game->technologyTypes()->where('code', 'RSR010')->sole();

        $this->assertNotNull($mythicalFarm->required_facility_type_id);
        $this->assertSame('Research', $mythicalFarm->requiredFacilityType?->name);
    }

    /**
     * A starting technology costs nothing: it is on the tree so its split pieces
     * can be tracked, not because anybody pays for it.
     */
    public function test_a_starting_technology_is_free(): void
    {
        $game = Game::factory()->create();

        $power = $game->technologyTypes()->where('code', 'RAR035')->sole();

        $this->assertTrue($power->isFree());
        $this->assertStringContainsString('Part 1/4', $power->name);
    }

    public function test_the_common_technologies_belong_to_no_corporation(): void
    {
        $game = Game::factory()->create();

        $common = $game->technologyTypes()->where('tree', TechnologyBlueprint::COMMON)->get();

        $this->assertNotEmpty($common);

        foreach ($common as $technology) {
            $this->assertTrue($technology->isCommon());
            $this->assertNull($technology->corporation_id);
        }
    }

    public function test_control_can_read_the_card_lists(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->get("/control/games/{$game->id}/cards")
            ->assertOk();
    }

    public function test_a_player_cannot_read_the_card_lists(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/control/games/{$game->id}/cards")
            ->assertForbidden();
    }

    /**
     * Re-running the seeders is safe: a card Control has edited keeps its edit,
     * and nothing is duplicated.
     */
    public function test_reseeding_neither_duplicates_nor_overwrites(): void
    {
        $game = Game::factory()->create();

        $orc = $game->protectionCardTypes()->where('code', 'PS009')->sole();
        $orc->update(['cost' => 99]);

        app(SeedProtectionCards::class)->handle($game);
        app(SeedEquipmentCards::class)->handle($game);
        app(SeedTechnologies::class)->handle($game);

        $this->assertSame(
            count(ProtectionCardBlueprint::defaults()),
            $game->protectionCardTypes()->count(),
        );
        $this->assertSame(
            count(TechnologyBlueprint::defaults()),
            $game->technologyTypes()->count(),
        );
        $this->assertSame(99, $orc->fresh()?->cost);
    }
}
