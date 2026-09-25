<?php

namespace Tests\Feature;

use App\Actions\CreateDefaultRoster;
use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\PhaseType;
use App\Enums\Tracker;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\User;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use App\Support\GamePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A CEO building their own Corporation a Facility (rulebook 3.3.1).
 *
 * This used to be Control's alone, so a Corporation could neither build without
 * finding an organiser nor find out what building would cost. What these tests
 * hold onto is the boundary: CEOs are the only ones who build, for their own Corporation
 * and nobody else's, at the type sheet's price and nothing else, during Setup -
 * and Control keeps its overrides, a build at any price, nought included.
 */
class FacilityRequisitionTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    private FacilityType $security;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

        $this->corporation = Corporation::factory()->for($this->game)->create([
            'name' => 'Gordon',
            'credits' => 30,
        ]);

        $this->security = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::SECURITY)->sole();
    }

    /**
     * A player sitting in one of a Corporation's seats.
     */
    private function seat(Corporation $corporation, CharacterRole $role): User
    {
        $user = User::factory()->create();

        $this->game->characters()->create([
            'corporation_id' => $corporation->id,
            'name' => $corporation->name.' '.$role->label(),
            'role' => $role,
        ])->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function requisition(User $user, ?Corporation $corporation = null, array $overrides = []): TestResponse
    {
        $corporation ??= $this->corporation;

        return $this->actingAs($user)->post("/corporations/{$corporation->id}/facilities", [
            'facility_type_id' => $this->security->id,
            'name' => 'Attercliffe Yard',
            ...$overrides,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sheetFor(User $user): ?array
    {
        return app(GamePresenter::class)->facilityBoard($this->game->fresh(), $user)['requisition'];
    }

    public function test_a_ceo_requisitions_a_facility_at_the_type_sheets_price(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);

        $this->requisition($ceo)->assertRedirect()->assertSessionHasNoErrors();

        /** @var Facility $facility */
        $facility = $this->corporation->facilities()->sole();

        $this->assertSame('Attercliffe Yard', $facility->name);
        $this->assertSame($this->security->id, $facility->facility_type_id);
        // Raised during turn 1's Setup, so it opens during turn 2's.
        $this->assertSame(2, $facility->available_from_turn);
        $this->assertSame(30 - $this->security->build_cost, $this->corporation->fresh()->credits);
    }

    public function test_the_ceo_is_named_in_the_ledger_against_the_build(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);

        $this->requisition($ceo);

        $adjustment = $this->game->trackerAdjustments()->latest('id')->sole();

        $this->assertSame(Tracker::CorporationCredits, $adjustment->tracker);
        $this->assertSame(-$this->security->build_cost, $adjustment->delta);
        $this->assertSame($ceo->id, $adjustment->actor_id);
    }

    public function test_a_player_cannot_name_their_own_price(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);

        $this->requisition($ceo, overrides: ['cost' => 0])->assertSessionHasNoErrors();

        $this->assertSame(30 - $this->security->build_cost, $this->corporation->fresh()->credits);
    }

    public function test_only_the_ceo_may_build(): void
    {
        foreach ([CharacterRole::Security, CharacterRole::Research] as $role) {
            $this->requisition($this->seat($this->corporation, $role))->assertForbidden();
        }

        $this->assertSame(0, $this->corporation->facilities()->count());
        $this->assertSame(30, $this->corporation->fresh()->credits);
    }

    public function test_a_ceo_cannot_build_for_a_rival(): void
    {
        $rival = Corporation::factory()->for($this->game)->create(['credits' => 30]);
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);

        $this->requisition($ceo, $rival)->assertForbidden();

        $this->assertSame(0, $rival->facilities()->count());
    }

    public function test_a_runner_cannot_requisition(): void
    {
        $user = User::factory()->create();
        $this->game->characters()->create([
            'name' => 'Jack Scanton',
            'role' => CharacterRole::Runner,
        ])->forceFill(['user_id' => $user->id])->save();

        $this->requisition($user)->assertForbidden();
    }

    public function test_a_game_that_is_not_running_takes_no_requisitions(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);
        $this->game->update(['status' => GameStatus::Draft]);

        $this->requisition($ceo)->assertForbidden();
    }

    public function test_a_requisition_outside_setup_is_refused(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);
        $engine = app(TurnEngine::class);
        $engine->advance($this->game->currentPhase());

        $this->assertSame(PhaseType::Action, $this->game->fresh()->currentPhase()?->type);

        $this->requisition($ceo)->assertSessionHasErrors('facility_type_id');

        $this->assertSame(0, $this->corporation->facilities()->count());
    }

    public function test_a_corporation_that_cannot_pay_is_refused(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);
        $this->corporation->update(['credits' => 2]);

        $this->requisition($ceo)->assertSessionHasErrors('cost');

        $this->assertSame(0, $this->corporation->facilities()->count());
        $this->assertSame(2, $this->corporation->fresh()->credits);
    }

    public function test_a_name_the_corporation_already_uses_is_refused(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);
        Facility::factory()->for($this->corporation)->for($this->security)->create(['name' => 'Attercliffe Yard']);

        $this->requisition($ceo)->assertSessionHasErrors('name');

        $this->assertSame(30, $this->corporation->fresh()->credits);
    }

    public function test_a_type_from_another_game_is_refused(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);
        $elsewhere = Game::factory()->create()->facilityTypes()->firstOrFail();

        $this->requisition($ceo, overrides: ['facility_type_id' => $elsewhere->id])
            ->assertSessionHasErrors('facility_type_id');
    }

    public function test_every_corporate_seat_reads_the_type_sheet_with_its_prices(): void
    {
        foreach ([CharacterRole::Ceo, CharacterRole::Security, CharacterRole::Research] as $role) {
            $sheet = $this->sheetFor($this->seat($this->corporation, $role));

            $this->assertNotNull($sheet);
            $this->assertSame($this->game->facilityTypes()->count(), count($sheet['types']));

            $line = collect($sheet['types'])->firstWhere('id', $this->security->id);
            $this->assertSame($this->security->build_cost, $line['build_cost']);
        }
    }

    public function test_only_the_ceo_is_offered_the_form(): void
    {
        $this->assertTrue($this->sheetFor($this->seat($this->corporation, CharacterRole::Ceo))['can_requisition']);
        $this->assertFalse($this->sheetFor($this->seat($this->corporation, CharacterRole::Security))['can_requisition']);
    }

    public function test_the_sheet_counts_what_the_corporation_already_has(): void
    {
        Facility::factory()->for($this->corporation)->for($this->security)->count(2)->create();

        $sheet = $this->sheetFor($this->seat($this->corporation, CharacterRole::Ceo));

        $this->assertSame(2, collect($sheet['types'])->firstWhere('id', $this->security->id)['owned']);
    }

    public function test_the_sheet_says_whether_requisitions_are_being_taken(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);

        $this->assertTrue($this->sheetFor($ceo)['open']);
        $this->assertSame(2, $this->sheetFor($ceo)['opens_on_turn']);

        app(TurnEngine::class)->advance($this->game->currentPhase());

        $this->assertFalse($this->sheetFor($ceo)['open']);
    }

    public function test_a_runner_gets_no_type_sheet(): void
    {
        $user = User::factory()->create();
        $this->game->characters()->create([
            'name' => 'Jack Scanton',
            'role' => CharacterRole::Runner,
        ])->forceFill(['user_id' => $user->id])->save();

        $this->assertNull($this->sheetFor($user));
    }

    public function test_control_builds_at_the_type_sheets_price_when_the_cost_is_left_blank(): void
    {
        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$this->game->id}/facilities", [
                'corporation_id' => $this->corporation->id,
                'facility_type_id' => $this->security->id,
                'name' => 'Attercliffe Yard',
                'mode' => 'requisition',
                'cost' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(30 - $this->security->build_cost, $this->corporation->fresh()->credits);
    }

    public function test_control_can_build_one_for_free(): void
    {
        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$this->game->id}/facilities", [
                'corporation_id' => $this->corporation->id,
                'facility_type_id' => $this->security->id,
                'name' => 'Attercliffe Yard',
                'mode' => 'requisition',
                'cost' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->corporation->facilities()->count());
        $this->assertSame(30, $this->corporation->fresh()->credits);
        $this->assertSame(0, $this->game->trackerAdjustments()->count());
    }

    public function test_a_corporation_with_a_build_discount_pays_less(): void
    {
        $this->corporation->update(['facility_build_discount' => 2]);
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);

        $this->requisition($ceo)->assertSessionHasNoErrors();

        $this->assertSame(30 - ($this->security->build_cost - 2), $this->corporation->fresh()->credits);
    }

    public function test_the_sheet_quotes_the_discounted_price(): void
    {
        $this->corporation->update(['facility_build_discount' => 2]);

        $sheet = $this->sheetFor($this->seat($this->corporation, CharacterRole::Security));
        $line = collect($sheet['types'])->firstWhere('id', $this->security->id);

        $this->assertSame(2, $sheet['build_discount']);
        $this->assertSame($this->security->build_cost, $line['build_cost']);
        $this->assertSame($this->security->build_cost - 2, $line['cost']);
    }

    public function test_a_discount_never_makes_a_build_pay_the_corporation(): void
    {
        $this->corporation->update(['facility_build_discount' => 100]);

        $this->assertSame(0, $this->corporation->facilityBuildCost($this->security));
    }

    public function test_only_mcm_opens_the_game_with_construction_leaders_discount(): void
    {
        $game = Game::factory()->create();
        app(CreateDefaultRoster::class)->handle($game);

        $discounts = $game->corporations()->pluck('facility_build_discount', 'name');

        $this->assertSame(2, (int) $discounts['McCullough Calibrated Mechanical']);
        $this->assertSame([0], $discounts->except('McCullough Calibrated Mechanical')->map(fn ($discount): int => (int) $discount)->unique()->values()->all());
    }

    public function test_control_charges_the_discounted_price_when_the_cost_is_left_blank(): void
    {
        $this->corporation->update(['facility_build_discount' => 2]);

        $this->actingAs(User::factory()->control()->create())
            ->post("/control/games/{$this->game->id}/facilities", [
                'corporation_id' => $this->corporation->id,
                'facility_type_id' => $this->security->id,
                'name' => 'Attercliffe Yard',
                'mode' => 'requisition',
                'cost' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(30 - ($this->security->build_cost - 2), $this->corporation->fresh()->credits);
    }

    public function test_control_sets_a_corporations_build_discount(): void
    {
        $this->actingAs(User::factory()->control()->create())
            ->patch("/control/games/{$this->game->id}/corporations/{$this->corporation->id}/build-discount", [
                'facility_build_discount' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $this->corporation->fresh()->facility_build_discount);
    }

    public function test_a_player_cannot_set_a_build_discount(): void
    {
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);

        $this->actingAs($ceo)
            ->patch("/control/games/{$this->game->id}/corporations/{$this->corporation->id}/build-discount", [
                'facility_build_discount' => 3,
            ])
            ->assertForbidden();

        $this->assertSame(0, (int) $this->corporation->fresh()->facility_build_discount);
    }

    public function test_a_corporation_from_another_game_is_not_reachable(): void
    {
        $elsewhere = Corporation::factory()->for(Game::factory())->create();

        $this->actingAs(User::factory()->control()->create())
            ->patch("/control/games/{$this->game->id}/corporations/{$elsewhere->id}/build-discount", [
                'facility_build_discount' => 3,
            ])
            ->assertNotFound();
    }
}
