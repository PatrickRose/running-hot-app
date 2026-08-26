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

    public function test_every_corporation_gets_the_facilities_from_its_briefing(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        /** @var array<int, array<string, mixed>> $configured */
        $configured = config('running_hot.corporations');

        $this->assertNotEmpty($configured);

        foreach ($configured as $entry) {
            $corporation = $game->corporations()->where('name', $entry['name'])->sole();

            $this->assertSame(
                count($entry['facilities']),
                $corporation->facilities()->count(),
                $entry['name'].' should open with the Facilities from its briefing.',
            );
        }
    }

    /**
     * The counts differ per Corporation, and the difference is mechanical.
     */
    public function test_the_starting_facilities_are_not_uniform(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        $counts = $game->corporations()
            ->orderBy('name')
            ->get()
            ->map(fn ($corporation): int => $corporation->facilities()->count())
            ->unique();

        $this->assertGreaterThan(1, $counts->count());
    }

    public function test_a_second_security_facility_widens_that_corporations_stacks(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        $defence = app(FacilityDefenceService::class);

        // DTC opens with two Security Facilities; everyone else has one.
        $dtc = $game->corporations()->where('name', 'Digital Tactical Control')->sole();
        $gordon = $game->corporations()->where('name', 'Gordon')->sole();

        $this->assertSame(5, $defence->slotsPerKind($dtc, ProtectionKind::Physical));
        $this->assertSame(7, $defence->slotsPerKind($dtc, ProtectionKind::Cyber));

        $this->assertSame(4, $defence->slotsPerKind($gordon, ProtectionKind::Physical));
        $this->assertSame(5, $defence->slotsPerKind($gordon, ProtectionKind::Cyber));
    }

    public function test_gordons_three_corporate_facilities_triple_its_storage(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        $gordon = $game->corporations()->where('name', 'Gordon')->sole();
        $geneq = $game->corporations()->where('name', 'Genetic Equity')->sole();

        $defence = app(FacilityDefenceService::class);

        $this->assertSame(6, $defence->technologyCapacityPerFacility($gordon));
        $this->assertSame(2, $defence->technologyCapacityPerFacility($geneq));
    }

    public function test_mccullough_opens_with_its_factory_discount(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        $defence = app(FacilityDefenceService::class);

        $mcm = $game->corporations()->where('name', 'McCullough Calibrated Mechanical')->sole();
        $gordon = $game->corporations()->where('name', 'Gordon')->sole();

        $this->assertSame(2, $defence->cardMoveDiscount($mcm));
        $this->assertSame(0, $defence->cardMoveDiscount($gordon));
    }

    /**
     * A Facility's name is what players call it all game, so each one is named
     * in the configuration rather than labelled from its type.
     */
    public function test_each_facility_gets_its_configured_name(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        /** @var array<int, array<string, mixed>> $configured */
        $configured = config('running_hot.corporations');

        foreach ($configured as $entry) {
            $corporation = $game->corporations()->where('name', $entry['name'])->sole();
            $names = $corporation->facilities()->pluck('name')->all();

            foreach ($entry['facilities'] as $planned) {
                $this->assertContains($planned['name'], $names);
            }
        }
    }

    public function test_names_are_distinct_within_a_corporation(): void
    {
        $game = $this->setUpGame();

        app(CreateDefaultFacilities::class)->handle($game);

        foreach ($game->corporations as $corporation) {
            $names = $corporation->facilities()->pluck('name');

            $this->assertSame($names->count(), $names->unique()->count());
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

    /**
     * A configured Facility with no name of its own still gets a usable one, so
     * a Corporation added to the roster later does not need naming first.
     */
    public function test_an_unnamed_facility_falls_back_to_its_corporation_and_type(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create([
            'name' => 'Sheffield Forgemasters',
        ]);

        config()->set('running_hot.corporations', [[
            'name' => 'Sheffield Forgemasters',
            'facilities' => [
                ['type' => FacilityTypeBlueprint::RESEARCH],
                ['type' => FacilityTypeBlueprint::RESEARCH],
                ['type' => FacilityTypeBlueprint::SECURITY],
            ],
        ]]);

        app(CreateDefaultFacilities::class)->handle($game);

        $this->assertSame(
            ['Sheffield Research', 'Sheffield Research 2', 'Sheffield Security'],
            $corporation->facilities()->orderBy('name')->pluck('name')->all(),
        );
    }

    /**
     * A Corporation the briefings say nothing about opens with none, rather
     * than with a guessed set.
     */
    public function test_an_unconfigured_corporation_gets_no_facilities(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Sheffield Forgemasters']);

        app(CreateDefaultFacilities::class)->handle($game);

        $this->assertSame(0, $corporation->facilities()->count());
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

    public function test_a_missing_facility_type_is_skipped_rather_than_invented(): void
    {
        $game = Game::factory()->create();
        Corporation::factory()->for($game)->create(['name' => 'Gordon']);

        $game->facilityTypes()
            ->where('key', FacilityTypeBlueprint::SECURITY)
            ->delete();

        $result = app(CreateDefaultFacilities::class)->handle($game);

        // Gordon's briefing is one Research, three Corporate and one Security.
        $this->assertSame(4, $result['facilities']);
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
