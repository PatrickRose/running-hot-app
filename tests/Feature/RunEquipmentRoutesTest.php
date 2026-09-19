<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\EquipmentCategory;
use App\Enums\GameStatus;
use App\Enums\ProtectionKind;
use App\Enums\RunnerSkill;
use App\Models\Character;
use App\Models\EquipmentCardType;
use App\Models\EquipmentHolding;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\Run;
use App\Models\Turn;
use App\Models\User;
use App\Services\Dice;
use App\Services\RunEngine;
use App\Support\RunPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDice;
use Tests\TestCase;

/**
 * Reaching the Equipment acts from the run screen (rulebook 3.4.1, 3.4.2).
 *
 * The engine already refused a fourth item and a second card in a step; what
 * these cover is the half that was missing, and the boundary the routes add to
 * it. `RunPolicy::act` only asks whether you are on this run - which every
 * Runner on it passes - so without the controller's own check a Runner could
 * kit out a gangmate or spend their cards.
 */
class RunEquipmentRoutesTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Turn $turn;

    private Facility $facility;

    private FakeDice $dice;

    protected function setUp(): void
    {
        parent::setUp();

        // Said rather than rolled: a test that quietly started throwing real
        // dice would fail intermittently for a reason nobody would look for.
        $this->dice = new FakeDice;
        $this->app->instance(Dice::class, $this->dice);

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->turn = Turn::factory()->create(['game_id' => $this->game->id]);
        $this->facility = Facility::factory()->create(['game_id' => $this->game->id]);

        FacilityProtectionCard::factory()->create([
            'facility_id' => $this->facility->id,
            'protection_card_type_id' => ProtectionCardType::factory()
                ->ofKind(ProtectionKind::Physical)
                ->create(['game_id' => $this->game->id])->id,
            'kind' => ProtectionKind::Physical,
            'position' => 1,
        ]);
    }

    // ------------------------------------------------------------------
    // Equipping
    // ------------------------------------------------------------------

    public function test_a_runner_equips_their_own_permanent_items(): void
    {
        [$user, $runner] = $this->player();
        $katana = $this->card(EquipmentCategory::Permanent, 'Katana');
        $this->give($runner, $katana);

        $run = $this->submitted($runner);

        $this->actingAs($user)
            ->post("/runs/{$run->id}/equipment", [
                'character_id' => $runner->id,
                'equipment_card_type_ids' => [$katana->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $run->equipment()->whereNull('pass')->count());
    }

    /**
     * An empty set is a real answer - taking nothing in - rather than a
     * validation failure, which is why the rule is `present` and not
     * `required`.
     */
    public function test_a_runner_may_take_nothing_in(): void
    {
        [$user, $runner] = $this->player();
        $card = $this->card(EquipmentCategory::Permanent);
        $this->give($runner, $card);

        $run = $this->submitted($runner);

        $engine = app(RunEngine::class);
        $engine->equip($run, $runner, [$card->id]);

        $this->actingAs($user)
            ->post("/runs/{$run->id}/equipment", [
                'character_id' => $runner->id,
                'equipment_card_type_ids' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $run->equipment()->whereNull('pass')->count());
    }

    /**
     * The boundary the route adds. Both are on the run, so both pass `act`.
     */
    public function test_a_runner_cannot_choose_a_gangmates_loadout(): void
    {
        [$user, $runner] = $this->player();
        [, $mate] = $this->player();

        $card = $this->card(EquipmentCategory::Permanent);
        $this->give($mate, $card);

        $run = app(RunEngine::class)->submit($this->turn, $this->facility, $runner, [$mate->id]);

        $this->actingAs($user)
            ->post("/runs/{$run->id}/equipment", [
                'character_id' => $mate->id,
                'equipment_card_type_ids' => [$card->id],
            ])
            ->assertForbidden();

        $this->assertSame(0, $run->equipment()->count());
    }

    public function test_a_stranger_cannot_equip_anybody(): void
    {
        [, $runner] = $this->player();
        $card = $this->card(EquipmentCategory::Permanent);
        $this->give($runner, $card);

        $run = $this->submitted($runner);

        $this->actingAs(User::factory()->create())
            ->post("/runs/{$run->id}/equipment", [
                'character_id' => $runner->id,
                'equipment_card_type_ids' => [$card->id],
            ])
            ->assertForbidden();
    }

    public function test_control_may_equip_anybody(): void
    {
        [, $runner] = $this->player();
        $card = $this->card(EquipmentCategory::Permanent);
        $this->give($runner, $card);

        $run = $this->submitted($runner);

        $this->actingAs(User::factory()->control()->create())
            ->post("/runs/{$run->id}/equipment", [
                'character_id' => $runner->id,
                'equipment_card_type_ids' => [$card->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $run->equipment()->whereNull('pass')->count());
    }

    /**
     * The engine's refusal has to reach the screen, since the picker is drawn
     * only while the run is Submitted and Control can still get here after.
     */
    public function test_equipping_after_the_run_has_gone_in_is_refused(): void
    {
        [$user, $runner] = $this->player();
        $card = $this->card(EquipmentCategory::Permanent);
        $this->give($runner, $card);

        $run = app(RunEngine::class)->begin($this->submitted($runner));

        $this->actingAs($user)
            ->post("/runs/{$run->id}/equipment", [
                'character_id' => $runner->id,
                'equipment_card_type_ids' => [$card->id],
            ])
            ->assertSessionHasErrors('equipment');
    }

    // ------------------------------------------------------------------
    // Playing a card
    // ------------------------------------------------------------------

    public function test_a_runner_plays_their_own_card(): void
    {
        [$user, $runner] = $this->player();
        $boost = $this->card(EquipmentCategory::SingleUse, 'Boost');
        $this->give($runner, $boost);

        $run = app(RunEngine::class)->begin($this->submitted($runner));

        $this->actingAs($user)
            ->post("/runs/{$run->id}/equipment/play", [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $boost->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $run->equipment()->whereNotNull('pass')->count());
        // Playing spends the copy: both consumable categories go back to
        // Control afterwards.
        $this->assertSame(0, $runner->equipmentCopiesOf($boost->id));
    }

    public function test_a_second_card_in_the_same_step_is_refused(): void
    {
        [$user, $runner] = $this->player();
        $first = $this->card(EquipmentCategory::SingleUse, 'Boost');
        $second = $this->card(EquipmentCategory::SingleUse, 'Flare');
        $this->give($runner, $first);
        $this->give($runner, $second);

        $run = app(RunEngine::class)->begin($this->submitted($runner));

        $this->actingAs($user)->post("/runs/{$run->id}/equipment/play", [
            'character_id' => $runner->id,
            'equipment_card_type_id' => $first->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post("/runs/{$run->id}/equipment/play", [
            'character_id' => $runner->id,
            'equipment_card_type_id' => $second->id,
        ])->assertSessionHasErrors('equipment');

        $this->assertSame(1, $runner->equipmentCopiesOf($second->id));
    }

    public function test_a_permanent_card_cannot_be_played(): void
    {
        [$user, $runner] = $this->player();
        $katana = $this->card(EquipmentCategory::Permanent, 'Katana');
        $this->give($runner, $katana);

        $run = app(RunEngine::class)->begin($this->submitted($runner));

        $this->actingAs($user)
            ->post("/runs/{$run->id}/equipment/play", [
                'character_id' => $runner->id,
                'equipment_card_type_id' => $katana->id,
            ])
            ->assertSessionHasErrors('equipment');
    }

    public function test_a_runner_cannot_play_a_gangmates_card(): void
    {
        [$user, $runner] = $this->player();
        [, $mate] = $this->player();

        $boost = $this->card(EquipmentCategory::SingleUse, 'Boost');
        $this->give($mate, $boost);

        $run = app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $runner, [$mate->id]),
        );

        $this->actingAs($user)
            ->post("/runs/{$run->id}/equipment/play", [
                'character_id' => $mate->id,
                'equipment_card_type_id' => $boost->id,
            ])
            ->assertForbidden();

        $this->assertSame(1, $mate->equipmentCopiesOf($boost->id));
    }

    // ------------------------------------------------------------------
    // What each side is sent
    // ------------------------------------------------------------------

    /**
     * Security reading the Runners' kit would know exactly what was coming
     * down the corridor, so they get none of it - equipped items included.
     * This is the one place the presenter's `privileged` flag is the wrong
     * question, since it means "the Security side or Control".
     */
    public function test_security_is_sent_no_runner_equipment(): void
    {
        [, $runner] = $this->player();
        $card = $this->card(EquipmentCategory::Permanent, 'Katana');
        $this->give($runner, $card);

        $engine = app(RunEngine::class);
        $run = $this->submitted($runner);
        $engine->equip($run, $runner, [$card->id]);

        // Security cannot see a run that has not gone in yet (3.3.5, 3.4.1),
        // so it has to be under way before there is a payload to check.
        $engine->begin($run);

        $security = $this->securitySeat();

        $payload = $this->runsDefendedBy($security);

        $this->assertNotSame([], $payload);
        $this->assertNull($payload[0]['equipment']);
        $this->assertStringNotContainsString('Katana', json_encode($payload) ?: '');
    }

    /**
     * A Runner sees what the group has equipped - face up on the table at
     * 3.4.1 - and only their own hand.
     */
    public function test_a_runner_sees_the_group_loadout_but_only_their_own_hand(): void
    {
        [$user, $runner] = $this->player();
        [, $mate] = $this->player();

        $mine = $this->card(EquipmentCategory::Permanent, 'Armour');
        $theirs = $this->card(EquipmentCategory::Permanent, 'Katana');
        $unplayed = $this->card(EquipmentCategory::SingleUse, 'Smoke Bomb');

        $this->give($runner, $mine);
        $this->give($mate, $theirs);
        $this->give($mate, $unplayed);

        $engine = app(RunEngine::class);
        $run = $engine->submit($this->turn, $this->facility, $runner, [$mate->id]);
        $engine->equip($run, $runner, [$mine->id]);
        $engine->equip($run, $mate, [$theirs->id]);

        $payload = $this->runsFor($user);
        $equipment = $payload[0]['equipment'];

        $this->assertNotNull($equipment);

        // Both loadouts, because both are on the table.
        $loadouts = collect($equipment['equipped'])->pluck('cards', 'name')
            ->map(fn (array $cards): array => array_column($cards, 'name'));
        $this->assertSame(['Armour'], $loadouts[$runner->name]);
        $this->assertSame(['Katana'], $loadouts[$mate->name]);

        // One hand, and it is theirs.
        $this->assertSame(
            [$runner->name],
            array_column($equipment['hands'], 'name'),
        );

        // And the gangmate's unplayed card is nowhere in the payload.
        $this->assertStringNotContainsString('Smoke Bomb', json_encode($payload) ?: '');
    }

    // ------------------------------------------------------------------
    // What a card is doing to a skill (3.4.1)
    // ------------------------------------------------------------------

    /**
     * The whole reason this is not a RollModifier: a skill is halved on the
     * way into the pool for everybody who is not leading (3.4.2), so the same
     * "+2 Brute" is worth two dice to the Leader and one to anybody else.
     */
    public function test_an_adjustment_is_worth_its_full_value_to_the_leader_and_half_to_everybody_else(): void
    {
        [$user, $leader] = $this->player();
        [, $mate] = $this->player();

        $engine = app(RunEngine::class);
        $run = $engine->begin(
            $engine->submit($this->turn, $this->facility, $leader, [$mate->id]),
        );

        // Both start on Brawn 2: the Leader brings 2, the other brings 1.
        $before = $this->poolFor($user)['brawn'];
        $this->assertSame(2, $before['leader']);
        $this->assertSame(3, $before['total']);

        $engine->adjustSkills($run, $leader, brawn: 2, hack: 0);
        $engine->adjustSkills($run, $mate, brawn: 2, hack: 0);

        $after = $this->poolFor($user)['brawn'];

        // The Leader's 2 becomes 4 dice; the other's 4 halves to 2.
        $this->assertSame(4, $after['leader']);
        $this->assertSame(6, $after['total']);
    }

    /**
     * And the pool that is quoted is the pool that is thrown. A second
     * implementation reading the character's own column would have the screen
     * promise dice the roll never made.
     */
    public function test_the_rolled_pool_matches_the_quoted_one(): void
    {
        [$user, $leader] = $this->player();

        $engine = app(RunEngine::class);
        $run = $engine->begin($this->submitted($leader));

        $engine->adjustSkills($run, $leader, brawn: 3, hack: 0);

        $quoted = $this->poolFor($user)['brawn']['total'];
        $this->assertSame(5, $quoted);

        $engine->activate($run->refresh(), true);

        // Security's roll first, then the Runners' - the pool under test.
        $this->dice->willRoll(1, 1);
        $engine->defend($run->refresh(), 1);

        $this->dice->willRoll($quoted, 8);

        $outcome = $engine->challenge($run->refresh(), RunnerSkill::Brawn);

        $this->assertSame($quoted, $outcome->runnersRoll->pool);
    }

    /**
     * It is the run's number, not the character's. Brawn is a Tracker, and a
     * Shiv carried into one Facility must not leave the ledger claiming a
     * Runner grew stronger and never got weaker.
     */
    public function test_an_adjustment_does_not_touch_the_characters_own_skills(): void
    {
        [, $runner] = $this->player();

        $engine = app(RunEngine::class);
        $run = $engine->begin($this->submitted($runner));

        $engine->adjustSkills($run, $runner, brawn: 5, hack: 5);

        $this->assertSame(2, $runner->refresh()->brawn);
        $this->assertSame(2, $runner->hack);
    }

    public function test_a_runner_sets_their_own_skills_over_the_route(): void
    {
        [$user, $runner] = $this->player();

        $run = app(RunEngine::class)->begin($this->submitted($runner));

        $this->actingAs($user)
            ->post("/runs/{$run->id}/skills", [
                'character_id' => $runner->id,
                'brawn_adjustment' => 1,
                'hack_adjustment' => -1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $participant = $run->participants()->where('character_id', $runner->id)->sole();

        $this->assertSame(1, $participant->brawn_adjustment);
        $this->assertSame(-1, $participant->hack_adjustment);
    }

    public function test_a_runner_cannot_set_a_gangmates_skills(): void
    {
        [$user, $runner] = $this->player();
        [, $mate] = $this->player();

        $engine = app(RunEngine::class);
        $run = $engine->begin(
            $engine->submit($this->turn, $this->facility, $runner, [$mate->id]),
        );

        $this->actingAs($user)
            ->post("/runs/{$run->id}/skills", [
                'character_id' => $mate->id,
                'brawn_adjustment' => 4,
                'hack_adjustment' => 0,
            ])
            ->assertForbidden();

        $this->assertSame(
            0,
            $run->participants()->where('character_id', $mate->id)->sole()->brawn_adjustment,
        );
    }

    public function test_control_may_set_anybodys_skills(): void
    {
        [, $runner] = $this->player();

        $run = app(RunEngine::class)->begin($this->submitted($runner));

        $this->actingAs(User::factory()->control()->create())
            ->post("/runs/{$run->id}/skills", [
                'character_id' => $runner->id,
                'brawn_adjustment' => 2,
                'hack_adjustment' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            2,
            $run->participants()->where('character_id', $runner->id)->sole()->brawn_adjustment,
        );
    }

    /**
     * Setting replaces, so a number typed wrong is corrected by sending the
     * right one rather than by working out the difference.
     */
    public function test_setting_replaces_rather_than_adds(): void
    {
        [$user, $runner] = $this->player();

        $run = app(RunEngine::class)->begin($this->submitted($runner));

        foreach ([3, 1] as $value) {
            $this->actingAs($user)->post("/runs/{$run->id}/skills", [
                'character_id' => $runner->id,
                'brawn_adjustment' => $value,
                'hack_adjustment' => 0,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(
            1,
            $run->participants()->where('character_id', $runner->id)->sole()->brawn_adjustment,
        );
    }

    /**
     * A skill cannot take dice off the rest of the group: a Runner talked into
     * a card costing more than they have contributes nothing instead.
     */
    public function test_a_penalty_larger_than_the_skill_floors_at_nothing(): void
    {
        [$user, $runner] = $this->player();

        $engine = app(RunEngine::class);
        $run = $engine->begin($this->submitted($runner));

        $engine->adjustSkills($run, $runner, brawn: -9, hack: 0);

        $this->assertSame(0, $this->poolFor($user)['brawn']['total']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function poolFor(User $user): array
    {
        /** @var array<string, array<string, mixed>> $pool */
        $pool = $this->runsFor($user)[0]['dice_pool'];

        return $pool;
    }

    /**
     * The runs this player is *on*, which is the Runners' side of the payload.
     *
     * @return array<int, array<string, mixed>>
     */
    private function runsFor(User $user): array
    {
        /** @var array<int, array<string, mixed>> $yours */
        $yours = app(RunPresenter::class)->forPlayer($this->game, $user)['yours'];

        return $yours;
    }

    /**
     * And the runs coming at their Facilities, which is Security's.
     *
     * @return array<int, array<string, mixed>>
     */
    private function runsDefendedBy(User $user): array
    {
        /** @var array<int, array<string, mixed>> $defending */
        $defending = app(RunPresenter::class)->forPlayer($this->game, $user)['defending'];

        return $defending;
    }

    /**
     * @return array{0: User, 1: Character}
     */
    private function player(): array
    {
        $user = User::factory()->create();

        $runner = Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
            'brawn' => 2,
            'hack' => 2,
        ]);

        return [$user, $runner];
    }

    private function securitySeat(): User
    {
        $user = User::factory()->create();

        Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'corporation_id' => $this->facility->corporation_id,
            'role' => CharacterRole::Security,
        ]);

        return $user;
    }

    private function card(EquipmentCategory $category, ?string $name = null): EquipmentCardType
    {
        return EquipmentCardType::factory()->create(array_filter([
            'game_id' => $this->game->id,
            'category' => $category,
            'name' => $name,
        ]));
    }

    private function give(Character $runner, EquipmentCardType $card, int $copies = 1): void
    {
        EquipmentHolding::create([
            'character_id' => $runner->id,
            'equipment_card_type_id' => $card->id,
            'copies' => $copies,
        ]);
    }

    private function submitted(Character $runner): Run
    {
        return app(RunEngine::class)->submit($this->turn, $this->facility, $runner);
    }
}
