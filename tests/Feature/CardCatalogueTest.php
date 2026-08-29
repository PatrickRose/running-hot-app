<?php

namespace Tests\Feature;

use App\Actions\SeedEquipmentCards;
use App\Actions\SeedProtectionCards;
use App\Actions\SeedTechnologies;
use App\Enums\EquipmentCategory;
use App\Models\Game;
use App\Models\TechnologyType;
use App\Models\User;
use App\Support\CardImage;
use App\Support\EquipmentCardBlueprint;
use App\Support\GamePresenter;
use App\Support\ProtectionCardBlueprint;
use App\Support\TechnologyBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use SplFileInfo;
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

    /** @var array<int, string> */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            File::delete($path);
        }

        $this->written = [];
        CardImage::flush();

        parent::tearDown();
    }

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    /**
     * Put a file in the artwork directory for the duration of one test.
     *
     * Refuses to write over something already there. The game's real artwork is
     * committed to this repository, so a test that overwrote a real file would
     * delete it again on the way out - which is exactly what happened once.
     */
    private function writeArtwork(string $file): void
    {
        $directory = public_path(CardImage::DIRECTORY);
        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$file;

        $this->assertFileDoesNotExist(
            $path,
            $file.' is real artwork. Use a code no card has.',
        );

        File::put($path, 'not really an image');
        $this->written[] = $path;

        CardImage::flush();
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

    /**
     * The card sheet has a cost column, but the Corporation shop does not work
     * the way it suggests, so seeding those numbers would encode a pricing model
     * the game does not use. Cards arrive unpriced and the shop brings its own
     * pricing when it is built.
     */
    public function test_the_seeded_cards_carry_no_shop_prices(): void
    {
        $game = Game::factory()->create();

        $this->assertSame(
            $game->protectionCardTypes()->count(),
            $game->protectionCardTypes()->whereNull('cost')->count(),
        );
        $this->assertSame(
            $game->equipmentCardTypes()->count(),
            $game->equipmentCardTypes()->whereNull('cost')->count(),
        );
    }

    /**
     * A Charge is nothing to do with buying a card: it is Credits Security
     * spends during a Run, printed on the card, and it survives.
     */
    public function test_a_charge_keeps_its_printed_cost(): void
    {
        $game = Game::factory()->create();

        $orc = $game->protectionCardTypes()->where('code', 'PS003')->sole();

        $this->assertSame(1, $orc->charge_cost);
        $this->assertSame('2 alert, 1 wound', $orc->charge_consequence);
        $this->assertTrue($orc->hasCharge());
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
     * Control can still put a figure on a card while the market is unbuilt, and
     * that is the only way one gets a price.
     */
    public function test_control_can_price_a_card_by_hand(): void
    {
        $game = Game::factory()->create();

        $bypass = $game->equipmentCardTypes()->where('code', 'ERS021')->sole();

        $this->assertFalse($bypass->isOnSale());

        $bypass->update(['cost' => 6]);

        $this->assertTrue($bypass->fresh()?->isOnSale());
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

    /**
     * A research card is printed proposal side up and flipped over once it has
     * been researched (rulebook 3.2.2), so it has two faces filed as _F and _B.
     * Both are public, so both reach the page.
     *
     * Written against a code no real card uses. The game's own artwork is
     * committed, so a test writing over a real code would delete that artwork
     * when it cleaned up after itself.
     */
    public function test_a_technology_carries_both_of_its_faces(): void
    {
        $game = Game::factory()->create();

        TechnologyType::factory()->for($game)->create(['code' => 'ZZ901', 'name' => 'Two faced']);
        TechnologyType::factory()->for($game)->create(['code' => 'ZZ902', 'name' => 'One faced']);

        $this->writeArtwork('ZZ901_F.webp');
        $this->writeArtwork('ZZ901_B.webp');
        $this->writeArtwork('ZZ902_F.webp');

        $technologies = collect(app(GamePresenter::class)->technologyTypes($game));

        $both = $technologies->firstWhere('code', 'ZZ901');
        $this->assertSame('/images/cards/ZZ901_F.webp', $both['image_path']);
        $this->assertSame('/images/cards/ZZ901_B.webp', $both['back_image_path']);

        // A card with one face reports no back rather than repeating its front,
        // or the interface would offer a flip that changed nothing.
        $one = $technologies->firstWhere('code', 'ZZ902');
        $this->assertSame('/images/cards/ZZ902_F.webp', $one['image_path']);
        $this->assertNull($one['back_image_path']);
    }

    /**
     * Every file in the artwork directory belongs to a card the catalogue
     * seeds, so a picture filed under a mistyped code is noticed rather than
     * silently never shown.
     */
    public function test_the_committed_artwork_all_belongs_to_a_card(): void
    {
        $game = Game::factory()->create();

        $claimed = collect()
            ->concat($game->protectionCardTypes()->pluck('code'))
            ->concat($game->equipmentCardTypes()->pluck('code'))
            ->concat($game->technologyTypes()->pluck('code'))
            ->filter()
            ->flatMap(fn (string $code): array => [
                CardImage::fileFor($code),
                CardImage::fileFor($code, CardImage::BACK),
            ])
            ->filter()
            ->unique();

        $onDisk = collect(File::files(public_path(CardImage::DIRECTORY)))
            ->map(fn (SplFileInfo $file): string => $file->getFilename())
            ->reject(fn (string $file): bool => str_starts_with($file, '.'));

        $this->assertSame(
            [],
            $onDisk->diff($claimed)->values()->all(),
            'Artwork is filed under a code no card in the catalogue has.',
        );
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
