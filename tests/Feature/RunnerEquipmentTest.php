<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\EquipmentCategory;
use App\Enums\ProtectionKind;
use App\Enums\RunConsequence;
use App\Enums\RunnerSkill;
use App\Enums\RunStep;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\EquipmentCardType;
use App\Models\EquipmentHolding;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\Run;
use App\Models\RunEvent;
use App\Models\Turn;
use App\Services\Dice;
use App\Services\RunEngine;
use App\Support\Runs\RollModifiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeDice;
use Tests\TestCase;

/**
 * What a Runner carries into a Facility (rulebook 3.4.1).
 *
 * Three categories that differ in *when* a card is used: a Permanent item is
 * equipped before the run and capped at three, and a This-run or Single-use
 * card is played during a step and returned to Control afterwards.
 */
class RunnerEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private FakeDice $dice;

    private Game $game;

    private Turn $turn;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dice = new FakeDice;
        $this->app->instance(Dice::class, $this->dice);

        $this->game = Game::factory()->create();
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
    // Equipping before the run (3.4.1)
    // ------------------------------------------------------------------

    public function test_a_runner_equips_permanent_items_before_the_run(): void
    {
        $runner = $this->runner();
        $katana = $this->card(EquipmentCategory::Permanent, 'Katana');
        $this->give($runner, $katana);

        $run = $this->submitted($runner);

        $this->engine()->equip($run, $runner, [$katana->id]);

        $this->assertSame(1, $run->equipment()->whereNull('pass')->count());

        // Equipping spends nothing: a Permanent item comes home with its owner.
        $this->assertSame(1, $runner->equipmentCopiesOf($katana->id));
    }

    /**
     * "You may only equip 3 permanent items."
     */
    public function test_a_fourth_permanent_item_is_refused(): void
    {
        $runner = $this->runner();
        $cards = collect(range(1, 4))->map(function (int $n) use ($runner): EquipmentCardType {
            $card = $this->card(EquipmentCategory::Permanent, 'Item '.$n);
            $this->give($runner, $card);

            return $card;
        });

        $run = $this->submitted($runner);

        $this->expectException(ValidationException::class);

        $this->engine()->equip($run, $runner, $cards->pluck('id')->all());
    }

    /**
     * "You may only equip one copy of each card (by title)" - by title rather
     * than by card, which is the rulebook's own wording and matters because two
     * codes can print the same name.
     */
    public function test_two_cards_of_the_same_title_are_refused(): void
    {
        $runner = $this->runner();
        $one = $this->card(EquipmentCategory::Permanent, 'Doppleganger');
        $two = $this->card(EquipmentCategory::Permanent, 'Doppleganger');
        $this->give($runner, $one);
        $this->give($runner, $two);

        $run = $this->submitted($runner);

        try {
            $this->engine()->equip($run, $runner, [$one->id, $two->id]);
            $this->fail('Two cards of one title should have been refused.');
        } catch (ValidationException $refusal) {
            $this->assertStringContainsString('one copy of each card', implode(' ', $refusal->errors()['equipment']));
        }
    }

    public function test_a_runner_cannot_equip_a_card_they_are_not_carrying(): void
    {
        $runner = $this->runner();
        $card = $this->card(EquipmentCategory::Permanent);

        $run = $this->submitted($runner);

        $this->expectException(ValidationException::class);

        $this->engine()->equip($run, $runner, [$card->id]);
    }

    /**
     * Setting the loadout replaces it, so a Runner changing their mind passes
     * the corrected set rather than looking for an unequip.
     */
    public function test_equipping_again_replaces_the_loadout(): void
    {
        $runner = $this->runner();
        $first = $this->card(EquipmentCategory::Permanent, 'Katana');
        $second = $this->card(EquipmentCategory::Permanent, 'Neural interface');
        $this->give($runner, $first);
        $this->give($runner, $second);

        $run = $this->submitted($runner);

        $this->engine()->equip($run, $runner, [$first->id]);
        $this->engine()->equip($run, $runner, [$second->id]);

        $this->assertSame(
            [$second->id],
            $run->equipment()->whereNull('pass')->pluck('equipment_card_type_id')->all(),
        );
    }

    public function test_a_single_use_card_cannot_be_equipped_beforehand(): void
    {
        $runner = $this->runner();
        $card = $this->card(EquipmentCategory::SingleUse);
        $this->give($runner, $card);

        $run = $this->submitted($runner);

        $this->expectException(ValidationException::class);

        $this->engine()->equip($run, $runner, [$card->id]);
    }

    // ------------------------------------------------------------------
    // Playing one during the run (3.4.1, 3.4.2)
    // ------------------------------------------------------------------

    /**
     * Both are "returned to Control" afterwards, so playing one spends it.
     */
    public function test_playing_a_single_use_card_spends_the_copy(): void
    {
        $runner = $this->runner();
        $card = $this->card(EquipmentCategory::SingleUse, 'Surge');
        $this->give($runner, $card, copies: 2);

        $run = $this->begun($runner);

        $played = $this->engine()->playEquipment($run, $runner, $card);

        $this->assertSame(1, $runner->equipmentCopiesOf($card->id));
        $this->assertSame(RunStep::Activate, $played->step);
        $this->assertSame(1, $played->pass);
        $this->assertDatabaseHas('run_events', ['type' => RunEvent::TYPE_EQUIPMENT_PLAYED]);
    }

    /**
     * "Each Runner in the Runner group may use one card during these steps",
     * which the worked examples make per Runner per *step*: Ryan plays a Boost
     * during Activate and "can not use another card until the next Activate
     * step".
     */
    public function test_only_one_card_per_runner_per_step(): void
    {
        $runner = $this->runner();
        $one = $this->card(EquipmentCategory::SingleUse, 'Surge');
        $two = $this->card(EquipmentCategory::SingleUse, 'Flare');
        $this->give($runner, $one);
        $this->give($runner, $two);

        $run = $this->begun($runner);

        $this->engine()->playEquipment($run, $runner, $one);

        try {
            $this->engine()->playEquipment($run->refresh(), $runner, $two);
            $this->fail('A second card in one step should have been refused.');
        } catch (ValidationException $refusal) {
            $this->assertStringContainsString('already played a card', implode(' ', $refusal->errors()['equipment']));
        }
    }

    /**
     * ...and the next step is a fresh one.
     */
    public function test_the_next_step_allows_another_card(): void
    {
        $runner = $this->runner();
        $one = $this->card(EquipmentCategory::SingleUse, 'Surge');
        $two = $this->card(EquipmentCategory::SingleUse, 'Flare');
        $this->give($runner, $one);
        $this->give($runner, $two);

        $run = $this->begun($runner);

        $this->engine()->playEquipment($run, $runner, $one);

        // On to the Challenge step: the card comes on and Security rolls.
        $this->engine()->activate($run->refresh());
        $this->dice->willRoll(1, 8);
        $this->engine()->defend($run->refresh(), 1);

        $played = $this->engine()->playEquipment($run->refresh(), $runner, $two);

        $this->assertSame(RunStep::Challenge, $played->step);
    }

    public function test_a_permanent_card_cannot_be_played_mid_run(): void
    {
        $runner = $this->runner();
        $card = $this->card(EquipmentCategory::Permanent);
        $this->give($runner, $card);

        $run = $this->begun($runner);

        $this->expectException(ValidationException::class);

        $this->engine()->playEquipment($run, $runner, $card);
    }

    // ------------------------------------------------------------------
    // What the dice hear (3.4.1)
    // ------------------------------------------------------------------

    /**
     * The whole point of the piece: an Equipment card that says "+2 Brute" or
     * "roll d8s" has nowhere to act otherwise, because the pool is derived and
     * the dice are rolled on the server.
     */
    public function test_declared_modifiers_change_the_dice_actually_thrown(): void
    {
        $runner = $this->runner(['brawn' => 2, 'wounds' => 1]);
        $run = $this->begun($runner);

        $this->engine()->activate($run->refresh());
        $this->dice->willRoll(1, 1);
        $this->engine()->defend($run->refresh(), 1);

        // Wounded, so the pool is 2 d6s. The card buys two more dice and turns
        // them back into d8s.
        $this->dice->willRoll(4, 8);

        $outcome = $this->engine()->challenge(
            $run->refresh(),
            RunnerSkill::Brawn,
            new RollModifiers(dice: 2, dieFaces: 8),
        );

        $this->assertSame(4, $outcome->runnersRoll->pool);
        $this->assertSame(8, $outcome->runnersRoll->die_faces);
        $this->assertSame(4, $outcome->runnersRoll->successes);
    }

    /**
     * Mind jack: "Retry any failed rolls once."
     *
     * The failures are thrown again and the new faces stand - a reroll that
     * kept the better of the two would be a different card.
     */
    public function test_a_reroll_throws_the_failures_again(): void
    {
        $runner = $this->runner(['brawn' => 4]);
        $run = $this->begun($runner);

        $this->engine()->activate($run->refresh());
        $this->dice->willRoll(1, 1);
        $this->engine()->defend($run->refresh(), 1);

        // Four dice: one hits, three miss. The three are thrown again and all
        // three land, so four successes off a roll that started with one.
        $this->dice->will([8, 1, 1, 1]);
        $this->dice->will([8, 8, 8]);

        $outcome = $this->engine()->challenge(
            $run->refresh(),
            RunnerSkill::Brawn,
            new RollModifiers(rerollFailures: true),
        );

        $this->assertSame(4, $outcome->runnersRoll->successes);
    }

    /**
     * Both on one roll, in the order they happen: the misses are thrown again
     * first, and only then is a face nudged. A +1 put on a die that was about
     * to be rerolled would be spent on a face nobody keeps.
     */
    public function test_a_reroll_happens_before_a_plus_one_lands(): void
    {
        $runner = $this->runner(['brawn' => 3]);
        $run = $this->begun($runner);

        $this->engine()->activate($run->refresh());
        $this->dice->willRoll(1, 1);
        $this->engine()->defend($run->refresh(), 1);

        // One hit and two misses; the two come back as a 4 and a 1.
        $this->dice->will([8, 1, 1]);
        $this->dice->will([4, 1]);

        $outcome = $this->engine()->challenge(
            $run->refresh(),
            RunnerSkill::Brawn,
            new RollModifiers(rerollFailures: true, bumps: 1),
        );

        // The +1 lands on the rerolled 4, which is the die it can rescue.
        $this->assertSame([8, 5, 1], $outcome->runnersRoll->faces);
        $this->assertSame(2, $outcome->runnersRoll->successes);
    }

    /**
     * Armour, and the cards like it: "Add +1 to one of your dice."
     *
     * A face nudged after it has been thrown rather than a die added to the
     * pool - which is the only way a 4 becomes the success it was one short
     * of, and it is a different thing from Mind jack's reroll above.
     */
    public function test_a_plus_one_lands_on_the_die_it_can_turn_into_a_success(): void
    {
        $runner = $this->runner(['brawn' => 4]);
        $run = $this->begun($runner);

        $this->engine()->activate($run->refresh());
        $this->dice->willRoll(1, 1);
        $this->engine()->defend($run->refresh(), 1);

        // One hit and a 4 that missed by one, plus two that are nowhere near.
        $this->dice->will([8, 4, 2, 1]);

        $outcome = $this->engine()->challenge(
            $run->refresh(),
            RunnerSkill::Brawn,
            new RollModifiers(bumps: 1),
        );

        // The +1 goes on the 4, not on the 2: two successes, not one.
        $this->assertSame(2, $outcome->runnersRoll->successes);
        $this->assertSame([8, 5, 2, 1], $outcome->runnersRoll->faces);
    }

    /**
     * Each +1 goes on a different die, because the card says "one of your
     * dice". Whether two may stack on one die is printed nowhere and is
     * Control's call.
     */
    public function test_two_plus_ones_land_on_two_different_dice(): void
    {
        $runner = $this->runner(['brawn' => 4]);
        $run = $this->begun($runner);

        $this->engine()->activate($run->refresh());
        $this->dice->willRoll(1, 1);
        $this->engine()->defend($run->refresh(), 1);

        $this->dice->will([4, 4, 1, 1]);

        $outcome = $this->engine()->challenge(
            $run->refresh(),
            RunnerSkill::Brawn,
            new RollModifiers(bumps: 2),
        );

        $this->assertSame([5, 5, 1, 1], $outcome->runnersRoll->faces);
        $this->assertSame(2, $outcome->runnersRoll->successes);
    }

    /**
     * A +1 with nowhere useful to go is spent rather than refused: the card
     * was played, and whether that was a waste is the player's business.
     */
    public function test_a_plus_one_with_nothing_to_rescue_is_still_spent(): void
    {
        $runner = $this->runner(['brawn' => 2]);
        $run = $this->begun($runner);

        $this->engine()->activate($run->refresh());
        $this->dice->willRoll(1, 1);
        $this->engine()->defend($run->refresh(), 1);

        $this->dice->will([8, 8]);

        $outcome = $this->engine()->challenge(
            $run->refresh(),
            RunnerSkill::Brawn,
            new RollModifiers(bumps: 1),
        );

        // Both already succeeded, so there is no failing die to nudge.
        $this->assertSame([8, 8], $outcome->runnersRoll->faces);
        $this->assertSame(2, $outcome->runnersRoll->successes);
    }

    /**
     * A card that costs dice cannot take the last one: "-1 die" on a pool of
     * one is a bad trade rather than an impossibility.
     */
    public function test_a_pool_is_never_reduced_below_one_die(): void
    {
        $runner = $this->runner(['brawn' => 1]);
        $run = $this->begun($runner);

        $this->engine()->activate($run->refresh());
        $this->dice->willRoll(1, 1);
        $this->engine()->defend($run->refresh(), 1);

        $this->dice->willRoll(1, 8);

        $outcome = $this->engine()->challenge(
            $run->refresh(),
            RunnerSkill::Brawn,
            new RollModifiers(dice: -5),
        );

        $this->assertSame(1, $outcome->runnersRoll->pool);
    }

    // ------------------------------------------------------------------
    // Carried out (3.4.2)
    // ------------------------------------------------------------------

    /**
     * "Any permanent Equipment that you played on this run will be given to the
     * Security player of the Corporation you are Running against."
     */
    public function test_being_carried_out_hands_permanent_equipment_to_security(): void
    {
        $corporation = Corporation::factory()->create(['game_id' => $this->game->id]);
        $this->facility->forceFill(['corporation_id' => $corporation->id])->save();

        $security = Character::factory()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $corporation->id,
            'role' => CharacterRole::Security,
        ]);

        $runner = $this->runner(['body' => 1]);
        $katana = $this->card(EquipmentCategory::Permanent, 'Katana');
        $this->give($runner, $katana);

        $run = $this->submitted($runner);
        $this->engine()->equip($run, $runner, [$katana->id]);
        $run = $this->engine()->begin($run);

        // A Wound that reaches Body carries them out there and then.
        $this->engine()->applyConsequence($run, RunConsequence::Wound, $runner, 1);

        $this->assertSame(0, $runner->equipmentCopiesOf($katana->id));
        $this->assertSame(1, $security->equipmentCopiesOf($katana->id));
    }

    /**
     * A Corporation with nobody in the Security seat is normal. The cards still
     * leave the Runner - they lost them - and where they went is Control's.
     */
    public function test_equipment_is_lost_even_with_no_security_player_to_take_it(): void
    {
        $runner = $this->runner(['body' => 1]);
        $katana = $this->card(EquipmentCategory::Permanent, 'Katana');
        $this->give($runner, $katana);

        $run = $this->submitted($runner);
        $this->engine()->equip($run, $runner, [$katana->id]);
        $run = $this->engine()->begin($run);

        $this->engine()->applyConsequence($run, RunConsequence::Wound, $runner, 1);

        $this->assertSame(0, $runner->equipmentCopiesOf($katana->id));
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function engine(): RunEngine
    {
        return app(RunEngine::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function runner(array $attributes = []): Character
    {
        return Character::factory()->create($attributes + [
            'game_id' => $this->game->id,
            'role' => CharacterRole::Runner,
            'brawn' => 2,
            'hack' => 2,
        ]);
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
        return $this->engine()->submit($this->turn, $this->facility, $runner);
    }

    private function begun(Character $runner): Run
    {
        return $this->engine()->begin($this->submitted($runner));
    }
}
