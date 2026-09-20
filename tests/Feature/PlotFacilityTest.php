<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\ProtectionKind;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Models\TrackerAdjustment;
use App\Models\User;
use App\Services\FacilityDefenceService;
use App\Services\TechnologyService;
use App\Services\TurnEngine;
use App\Support\Discord\FacilityListEmbed;
use App\Support\Discord\GuildBlueprint;
use App\Support\Discord\PlannedOverwrite;
use App\Support\FacilityTypeBlueprint;
use App\Support\GamePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Facilities Control builds for the Runners to hit (see App\Models\Facility).
 *
 * A Plot Facility is a Facility with no Corporation, which is the whole of the
 * mechanism - so what these tests pin down is everything that was a
 * Corporation's and now has nobody to belong to: the hand a card is installed
 * out of, the Credits a reorder is charged against, the budget's escrow, the
 * Security seat that defends it, and the category its Discord channels sit in.
 */
class PlotFacilityTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
    }

    private function control(): User
    {
        return User::factory()->control()->create();
    }

    private function type(string $key = FacilityTypeBlueprint::RESEARCH): FacilityType
    {
        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', $key)->sole();

        return $type;
    }

    private function plotFacility(string $name = 'Wincobank Substation'): Facility
    {
        return Facility::factory()
            ->plot($this->game)
            ->for($this->type())
            ->create(['name' => $name]);
    }

    private function card(ProtectionKind $kind = ProtectionKind::Physical): ProtectionCardType
    {
        return ProtectionCardType::factory()
            ->for($this->game)
            ->ofKind($kind)
            ->create();
    }

    private function defence(): FacilityDefenceService
    {
        return app(FacilityDefenceService::class);
    }

    public function test_control_builds_a_facility_that_belongs_to_nobody(): void
    {
        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/facilities", [
                'corporation_id' => '',
                'facility_type_id' => $this->type()->id,
                'name' => 'Wincobank Substation',
                'mode' => 'requisition',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        /** @var Facility $facility */
        $facility = $this->game->facilities()->where('name', 'Wincobank Substation')->sole();

        $this->assertNull($facility->corporation_id);
        $this->assertTrue($facility->isPlotFacility());
        $this->assertSame(Facility::INDEPENDENT_OWNER, $facility->ownerName());
    }

    /**
     * Nobody signs the slip, so the Setup-phase rule of 3.3.1 has nothing to
     * bite on: a plot target appears when the story needs one.
     */
    public function test_a_plot_facility_opens_at_once_and_costs_nobody_anything(): void
    {
        app(TurnEngine::class)->start($this->game);
        $before = TrackerAdjustment::query()->count();

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/facilities", [
                'corporation_id' => null,
                'facility_type_id' => $this->type()->id,
                'name' => 'Wincobank Substation',
                'cost' => 40,
                'mode' => 'requisition',
            ])
            ->assertSessionHasNoErrors();

        /** @var Facility $facility */
        $facility = $this->game->facilities()->plot()->sole();

        $this->assertTrue($facility->isAvailableOnTurn($this->game->currentTurn()?->number));
        $this->assertSame($before, TrackerAdjustment::query()->count());
    }

    public function test_two_plot_facilities_cannot_share_a_name(): void
    {
        $this->plotFacility('Wincobank Substation');

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/facilities", [
                'corporation_id' => '',
                'facility_type_id' => $this->type()->id,
                'name' => 'Wincobank Substation',
                'mode' => 'requisition',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $this->game->facilities()->plot()->count());
    }

    /**
     * The cap on a stack exists to make Security Facilities worth building, and
     * Control is not playing that economy.
     */
    public function test_a_plot_facility_takes_as_many_cards_as_control_installs(): void
    {
        $facility = $this->plotFacility();

        $this->assertNull($this->defence()->slotsFor($facility, ProtectionKind::Physical));
        $this->assertNull($this->defence()->slotsFor($facility, ProtectionKind::Cyber));

        for ($i = 0; $i < 6; $i++) {
            $this->defence()->install($facility, $this->card());
        }

        $this->assertCount(6, $this->defence()->stack($facility->fresh(), ProtectionKind::Physical));
    }

    /**
     * There is no hand to take a copy out of, and none to put one back into.
     */
    public function test_installing_into_a_plot_facility_spends_no_copy(): void
    {
        $facility = $this->plotFacility();
        $card = $this->card();

        $installed = $this->defence()->install($facility, $card);

        $this->assertSame(0, $card->holdings()->count());

        $cost = $this->defence()->remove($installed->fresh());

        $this->assertSame(0, $cost);
        $this->assertSame(0, $card->holdings()->count());
    }

    public function test_reordering_a_plot_facility_is_free(): void
    {
        $facility = $this->plotFacility();

        $first = $this->defence()->install($facility, $this->card());
        $second = $this->defence()->install($facility, $this->card());
        $third = $this->defence()->install($facility, $this->card());

        $quote = $this->defence()->quoteReorder(
            $facility->fresh(),
            ProtectionKind::Physical,
            [$second->id, $third->id, $first->id],
        );

        // The arrangement still says how many cards move - that is a fact about
        // it - but nothing is charged and no Factory discount is claimed.
        $this->assertGreaterThan(0, $quote['moved']);
        $this->assertSame(0, $quote['discount']);
        $this->assertSame(0, $quote['cost']);
        $this->assertTrue($quote['affordable']);

        $charged = $this->defence()->reorder(
            $facility->fresh(),
            ProtectionKind::Physical,
            [$second->id, $third->id, $first->id],
        );

        $this->assertSame(0, $charged);
    }

    /**
     * There is no Corporation to escrow from, so the budget is simply the
     * number Control writes - and nothing lands in the ledger, because no
     * tracker moved.
     */
    public function test_a_plot_facility_budget_escrows_nothing_and_returns_nothing(): void
    {
        $phase = app(TurnEngine::class)->start($this->game);
        $facility = $this->plotFacility();
        $before = TrackerAdjustment::query()->count();

        $state = $this->defence()->setSecurityBudget($facility, 12, $phase->turn);

        $this->assertSame(12, $state->security_budget);
        $this->assertSame($before, TrackerAdjustment::query()->count());

        $returned = $this->defence()->returnUnspentBudgets($phase->turn);

        $this->assertArrayNotHasKey($facility->name, $returned);
        $this->assertSame($before, TrackerAdjustment::query()->count());
        $this->assertNotNull($facility->stateForTurn($phase->turn)->fresh()->budget_returned_at);
    }

    public function test_a_plot_facility_stores_no_technologies(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create();
        $facility = $this->plotFacility();

        $holding = TechnologyHolding::factory()
            ->for($this->game)
            ->for($corporation)
            ->for(TechnologyType::factory()->for($this->game))
            ->create(['facility_id' => null]);

        $this->assertSame(0, app(TechnologyService::class)->capacityFor($facility));

        $this->expectException(ValidationException::class);

        app(TechnologyService::class)->place($holding, $facility);
    }

    /**
     * Nobody in the roster owns one, so nobody in the roster defends one.
     * Control still can, through the policy's own override.
     */
    public function test_no_player_may_defend_a_plot_facility(): void
    {
        $this->game->update(['status' => GameStatus::Running]);

        $corporation = Corporation::factory()->for($this->game)->create();
        $player = User::factory()->create();

        Character::factory()
            ->for($this->game)
            ->for($corporation)
            ->create(['role' => CharacterRole::Security, 'user_id' => $player->id]);

        $facility = $this->plotFacility();

        $this->assertFalse($player->can('defend', $facility));
        $this->assertTrue($this->control()->can('defend', $facility));
    }

    /**
     * Everybody sees it, because a group cannot name a target it was never told
     * about - and nobody gets a second tier on it, because nobody is inside.
     */
    public function test_every_player_sees_a_plot_facility_on_the_public_list(): void
    {
        $facility = $this->plotFacility();
        $player = User::factory()->create();

        $board = app(GamePresenter::class)->facilityBoard($this->game, $player);

        $this->assertNotNull($board['plot']);
        $this->assertSame(Facility::INDEPENDENT_OWNER, $board['plot']['name']);
        $this->assertSame(
            [$facility->name],
            array_column($board['plot']['facilities'], 'name'),
        );
    }

    public function test_a_game_with_no_plot_facilities_sends_no_plot_group(): void
    {
        $board = app(GamePresenter::class)->facilityBoard($this->game, null);

        $this->assertNull($board['plot']);
    }

    /**
     * The same rule the Corporations' embeds follow: a name, a type, whether it
     * is building, and nothing about what is in it.
     */
    public function test_the_facility_list_embed_carries_a_plot_facility_and_names_no_card(): void
    {
        $facility = $this->plotFacility();
        $card = $this->card();
        $this->defence()->install($facility, $card);

        $payload = FacilityListEmbed::payload($this->game->fresh());
        $json = json_encode($payload);

        $this->assertStringContainsString(Facility::INDEPENDENT_OWNER, $json);
        $this->assertStringContainsString($facility->name, $json);
        $this->assertStringNotContainsString($card->name, $json);
    }

    /**
     * Its channels sit in a category of their own, locked to Control: there is
     * no team role to open them to.
     */
    public function test_a_plot_facility_channel_is_control_only_and_in_its_own_category(): void
    {
        $facility = $this->plotFacility();

        $this->assertSame(
            GuildBlueprint::CATEGORY_PLOT_FACILITIES,
            GuildBlueprint::categoryKeyForFacility($facility),
        );

        $roleKeys = array_map(
            fn (PlannedOverwrite $overwrite): string => $overwrite->target,
            GuildBlueprint::facilityOverwrites($facility),
        );

        $this->assertSame(
            [PlannedOverwrite::EVERYONE, GuildBlueprint::ROLE_CONTROL],
            $roleKeys,
        );

        $channels = GuildBlueprint::channelsForFacility($facility);

        foreach ($channels as $channel) {
            $this->assertSame(GuildBlueprint::CATEGORY_PLOT_FACILITIES, $channel->parentKey);
        }
    }

    public function test_the_blueprint_adds_the_plot_category_only_when_there_is_one_to_hold(): void
    {
        $keys = fn (): array => array_map(
            fn ($channel): string => $channel->key,
            (new GuildBlueprint($this->game->fresh()))->channels(),
        );

        $this->assertNotContains(GuildBlueprint::CATEGORY_PLOT_FACILITIES, $keys());

        $this->plotFacility();

        $this->assertContains(GuildBlueprint::CATEGORY_PLOT_FACILITIES, $keys());
    }
}
