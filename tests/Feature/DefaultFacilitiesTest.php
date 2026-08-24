<?php

namespace Tests\Feature;

use App\Actions\CreateDefaultFacilities;
use App\Actions\CreateDefaultRoster;
use App\Enums\ProtectionKind;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\User;
use App\Services\FacilityDefenceService;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Each Corporation will begin with a number of Facilities and some basic
 * Protection Cards" (rulebook 3.3).
 */
class DefaultFacilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    protected function setUpGame(): Game
    {
        $game = Game::factory()->create();
        app(CreateDefaultRoster::class)->handle($game);

        return $game;
    }

    public function test_every_corporation_gets_the_configured_facilities(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        $expected = count(config('running_hot.facilities'));

        $this->assertGreaterThan(0, $expected);

        foreach ($game->corporations as $corporation) {
            $this->assertSame($expected, $corporation->facilities()->count());
        }
    }

    public function test_the_starting_facilities_are_open_straight_away(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        foreach ($game->facilities as $facility) {
            $this->assertSame(Facility::FIRST_TURN, $facility->available_from_turn);
            $this->assertTrue($facility->isAvailableOnTurn(null));
        }
    }

    public function test_building_the_starting_facilities_is_free(): void
    {
        $game = $this->setUpGame();

        $credits = $game->corporations()->orderBy('name')->pluck('credits', 'name');

        app(CreateDefaultFacilities::class)->handle($game);

        $this->assertSame(
            $credits->all(),
            $game->corporations()->orderBy('name')->pluck('credits', 'name')->all(),
        );
        $this->assertSame(0, $game->trackerAdjustments()->count());
    }

    public function test_a_facility_is_named_after_its_corporation(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create([
            'name' => 'McCullough Calibrated Mechanical',
        ]);

        app(CreateDefaultFacilities::class)->handle($game);

        foreach ($corporation->facilities as $facility) {
            $this->assertStringStartsWith('McCullough ', $facility->name);
        }
    }

    public function test_the_security_facility_widens_every_stack(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        $corporation = $game->corporations()->orderBy('name')->first();
        $this->assertNotNull($corporation);

        // Three by default, plus one for the starting Security Facility.
        $this->assertSame(
            FacilityDefenceService::BASE_SLOTS_PER_KIND + 1,
            app(FacilityDefenceService::class)->slotsPerKind($corporation),
        );
    }

    public function test_the_corporate_facility_makes_technology_storable(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        $corporation = $game->corporations()->orderBy('name')->first();
        $this->assertNotNull($corporation);

        $this->assertSame(
            2,
            app(FacilityDefenceService::class)->technologyCapacityPerFacility($corporation),
        );
    }

    public function test_the_card_catalogue_is_written(): void
    {
        $game = $this->setUpGame();

        $result = app(CreateDefaultFacilities::class)->handle($game);

        $this->assertSame(
            count(config('running_hot.protection_cards')),
            $game->protectionCardTypes()->count(),
        );
        $this->assertSame($game->protectionCardTypes()->count(), $result['card_types']);
    }

    public function test_every_starting_facility_opens_with_the_basic_cards(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        /** @var array<int, string> $basic */
        $basic = config('running_hot.installed_in_each');

        foreach ($game->facilities as $facility) {
            $installed = $facility->protectionCards()
                ->with('cardType')
                ->get()
                ->map(fn ($card): string => $card->cardType->name)
                ->all();

            $this->assertEqualsCanonicalizing($basic, $installed);
        }
    }

    public function test_the_basic_cards_land_in_the_right_stacks(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        $facility = $game->facilities()->firstOrFail();
        $defence = app(FacilityDefenceService::class);

        foreach (ProtectionKind::cases() as $kind) {
            foreach ($defence->stack($facility, $kind) as $card) {
                $this->assertSame($kind, $card->cardType->kind);
            }
        }
    }

    public function test_installing_the_basic_cards_is_free(): void
    {
        $game = $this->setUpGame();
        $corporation = $game->corporations()->orderBy('name')->firstOrFail();
        $before = $corporation->credits;

        app(CreateDefaultFacilities::class)->handle($game);

        $this->assertSame($before, $corporation->fresh()?->credits);
    }

    public function test_a_game_that_already_has_facilities_is_left_alone(): void
    {
        $game = $this->setUpGame();
        $action = app(CreateDefaultFacilities::class);

        $action->handle($game);
        $count = $game->facilities()->count();

        $result = $action->handle($game);

        $this->assertTrue($result['skipped']);
        $this->assertSame($count, $game->facilities()->count());
    }

    public function test_a_game_with_no_corporations_gets_the_catalogue_and_no_facilities(): void
    {
        $game = Game::factory()->create();

        $result = app(CreateDefaultFacilities::class)->handle($game);

        $this->assertSame(0, $result['facilities']);
        $this->assertGreaterThan(0, $result['card_types']);
    }

    public function test_a_renamed_facility_type_is_skipped_rather_than_invented(): void
    {
        $game = Game::factory()->create();
        Corporation::factory()->for($game)->create();

        $game->facilityTypes()
            ->where('key', FacilityTypeBlueprint::SECURITY)
            ->delete();

        $result = app(CreateDefaultFacilities::class)->handle($game);

        $this->assertSame(count(config('running_hot.facilities')) - 1, $result['facilities']);
    }

    public function test_creating_a_game_builds_the_facilities(): void
    {
        $this->actingAs($this->control())
            ->post('/control/games', ['name' => 'Procatorion'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $game = Game::query()->latest('id')->firstOrFail();

        $this->assertGreaterThan(0, $game->facilities()->count());
        $this->assertGreaterThan(0, $game->protectionCardTypes()->count());
    }

    public function test_an_empty_game_gets_no_facilities(): void
    {
        $this->actingAs($this->control())
            ->post('/control/games', [
                'name' => 'Procatorion',
                'skip_default_roster' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $game = Game::query()->latest('id')->firstOrFail();

        $this->assertSame(0, $game->facilities()->count());
        $this->assertSame(0, $game->protectionCardTypes()->count());
    }
}
