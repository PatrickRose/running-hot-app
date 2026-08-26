<?php

namespace Tests\Feature;

use App\Enums\ProtectionKind;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\User;
use App\Services\FacilityDefenceService;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProtectionCardStackTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
        $this->corporation = Corporation::factory()->for($this->game)->create(['credits' => 50]);
        $this->facility = $this->facilityOfType(FacilityTypeBlueprint::RESEARCH, 'Attercliffe Yard');
    }

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    protected function facilityOfType(string $key, string $name): Facility
    {
        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', $key)->sole();

        return Facility::factory()
            ->for($this->corporation)
            ->for($type)
            ->create(['name' => $name]);
    }

    /**
     * A card in the catalogue, with copies in the Corporation's hand.
     *
     * Installing takes a copy out of the hand, so a card nobody owns cannot be
     * installed. These tests are about the stacks rather than the holdings, so
     * they hand over more copies than any of them installs and let
     * ProtectionCardHoldingTest cover running out.
     */
    protected function card(string $name, ProtectionKind $kind = ProtectionKind::Physical): ProtectionCardType
    {
        return ProtectionCardType::factory()
            ->for($this->game)
            ->ofKind($kind)
            ->heldBy($this->corporation->id, 8)
            ->create(['name' => $name]);
    }

    protected function defence(): FacilityDefenceService
    {
        return app(FacilityDefenceService::class);
    }

    protected function slots(ProtectionKind $kind): int
    {
        return $this->defence()->slotsPerKind($this->corporation->fresh(), $kind);
    }

    /**
     * @return array<int, string>
     */
    protected function stackNames(ProtectionKind $kind = ProtectionKind::Physical): array
    {
        return $this->defence()
            ->stack($this->facility->fresh(), $kind)
            ->map(fn (FacilityProtectionCard $card): string => $card->cardType->name)
            ->all();
    }

    public function test_a_facility_has_three_slots_of_each_kind_by_default(): void
    {
        $this->assertSame(3, $this->slots(ProtectionKind::Physical));
        $this->assertSame(3, $this->slots(ProtectionKind::Cyber));
    }

    /**
     * The type sheet's Security Facility is asymmetric: 1 more physical and 2
     * more cyber card per Facility owned. Rulebook 3.3.4 says "1 more of each
     * type" and the type sheet supersedes it.
     */
    public function test_each_security_facility_grants_one_physical_and_two_cyber_slots(): void
    {
        $this->facilityOfType(FacilityTypeBlueprint::SECURITY, 'Tinsley Gate');
        $this->assertSame(4, $this->slots(ProtectionKind::Physical));
        $this->assertSame(5, $this->slots(ProtectionKind::Cyber));

        $this->facilityOfType(FacilityTypeBlueprint::SECURITY, 'Wicker Post');
        $this->assertSame(5, $this->slots(ProtectionKind::Physical));
        $this->assertSame(7, $this->slots(ProtectionKind::Cyber));
    }

    public function test_a_security_facility_still_building_does_not_widen_the_stacks(): void
    {
        app(TurnEngine::class)->start($this->game);

        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::SECURITY)->sole();

        Facility::factory()
            ->for($this->corporation)
            ->for($type)
            ->buildingUntilTurn(2)
            ->create(['name' => 'Tinsley Gate']);

        $this->assertSame(3, $this->slots(ProtectionKind::Physical));
        $this->assertSame(3, $this->slots(ProtectionKind::Cyber));
    }

    public function test_a_factory_takes_two_credits_off_a_reorder(): void
    {
        $this->facilityOfType(FacilityTypeBlueprint::FACTORY, 'Templeborough Works');
        $ids = $this->installAlphaBravoCharlie();

        // Reversing three cards moves two of them, and the Factory's discount
        // covers both.
        $cost = $this->defence()->reorder($this->facility->fresh(), ProtectionKind::Physical, [
            $ids['Charlie'], $ids['Bravo'], $ids['Alpha'],
        ]);

        $this->assertSame(0, $cost);
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->stackNames());
        $this->assertSame(50, $this->corporation->fresh()->credits);
    }

    public function test_a_discount_never_pays_a_corporation_to_reorder(): void
    {
        $this->facilityOfType(FacilityTypeBlueprint::FACTORY, 'Templeborough Works');
        $ids = $this->installAlphaBravoCharlie();

        // One card moves, against a discount of two.
        $cost = $this->defence()->reorder($this->facility->fresh(), ProtectionKind::Physical, [
            $ids['Bravo'], $ids['Charlie'], $ids['Alpha'],
        ]);

        $this->assertSame(0, $cost);
        $this->assertSame(50, $this->corporation->fresh()->credits);
    }

    public function test_a_mini_factory_takes_one_credit_off_a_reorder(): void
    {
        $this->facilityOfType(FacilityTypeBlueprint::MINI_FACTORY, 'Neepsend Shop');
        $ids = $this->installAlphaBravoCharlie();

        $cost = $this->defence()->reorder($this->facility->fresh(), ProtectionKind::Physical, [
            $ids['Charlie'], $ids['Bravo'], $ids['Alpha'],
        ]);

        $this->assertSame(1, $cost);
        $this->assertSame(49, $this->corporation->fresh()->credits);
    }

    /**
     * A stepping type is not worth its effect times the number you own: the
     * type sheet gives a Factory 2 Credits and "an additional 1 Credit discount
     * at 2, 3, 5, 8 etc Factories".
     */
    public function test_a_second_factory_is_worth_one_more_credit_not_two(): void
    {
        $this->facilityOfType(FacilityTypeBlueprint::FACTORY, 'Templeborough Works');
        $this->assertSame(2, $this->defence()->cardMoveDiscount($this->corporation->fresh()));

        $this->facilityOfType(FacilityTypeBlueprint::FACTORY, 'Brightside Works');
        $this->assertSame(3, $this->defence()->cardMoveDiscount($this->corporation->fresh()));

        $this->facilityOfType(FacilityTypeBlueprint::FACTORY, 'Carbrook Works');
        $this->assertSame(4, $this->defence()->cardMoveDiscount($this->corporation->fresh()));

        // The fourth crosses no threshold, so it is worth nothing.
        $this->facilityOfType(FacilityTypeBlueprint::FACTORY, 'Darnall Works');
        $this->assertSame(4, $this->defence()->cardMoveDiscount($this->corporation->fresh()));

        $this->facilityOfType(FacilityTypeBlueprint::FACTORY, 'Attercliffe Works');
        $this->assertSame(5, $this->defence()->cardMoveDiscount($this->corporation->fresh()));
    }

    public function test_technology_capacity_scales_with_the_corporate_facility_count(): void
    {
        $this->assertSame(0, $this->defence()->technologyCapacityPerFacility($this->corporation));

        $this->facilityOfType(FacilityTypeBlueprint::CORPORATE, 'Sheffield Spire');
        $this->assertSame(2, $this->defence()->technologyCapacityPerFacility($this->corporation->fresh()));

        $this->facilityOfType(FacilityTypeBlueprint::CORPORATE, 'Meadowhall Tower');
        $this->assertSame(4, $this->defence()->technologyCapacityPerFacility($this->corporation->fresh()));
    }

    public function test_installing_puts_the_new_card_outermost(): void
    {
        $this->defence()->install($this->facility, $this->card('Alpha'));
        $this->defence()->install($this->facility, $this->card('Bravo'));
        $this->defence()->install($this->facility, $this->card('Charlie'));

        // Runners meet the most recently installed card first.
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->stackNames());
    }

    public function test_installing_is_free(): void
    {
        $this->defence()->install($this->facility, $this->card('Alpha'));

        $this->assertSame(50, $this->corporation->fresh()->credits);
    }

    public function test_the_two_stacks_are_independent(): void
    {
        $this->defence()->install($this->facility, $this->card('Alpha'));
        $this->defence()->install($this->facility, $this->card('Firewall', ProtectionKind::Cyber));

        $this->assertSame(['Alpha'], $this->stackNames());
        $this->assertSame(['Firewall'], $this->stackNames(ProtectionKind::Cyber));
        $this->assertSame(1, $this->defence()->stack($this->facility, ProtectionKind::Cyber)->first()?->position);
    }

    public function test_a_full_stack_refuses_another_card(): void
    {
        foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
            $this->defence()->install($this->facility, $this->card($name));
        }

        $this->expectException(ValidationException::class);

        $this->defence()->install($this->facility, $this->card('Delta'));
    }

    public function test_a_full_stack_accepts_another_card_once_a_security_facility_opens(): void
    {
        foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
            $this->defence()->install($this->facility, $this->card($name));
        }

        $this->facilityOfType(FacilityTypeBlueprint::SECURITY, 'Tinsley Gate');
        $this->assertSame(4, $this->slots(ProtectionKind::Physical));

        $this->defence()->install($this->facility->fresh(), $this->card('Delta'));

        $this->assertSame(['Delta', 'Charlie', 'Bravo', 'Alpha'], $this->stackNames());
    }

    public function test_only_one_copy_of_a_card_title_per_facility(): void
    {
        $card = $this->card('Alpha');
        $this->defence()->install($this->facility, $card);

        $this->expectException(ValidationException::class);

        $this->defence()->install($this->facility->fresh(), $card);
    }

    public function test_the_same_card_may_be_installed_in_two_facilities(): void
    {
        $card = $this->card('Alpha');
        $other = $this->facilityOfType(FacilityTypeBlueprint::RESEARCH, 'Wicker Post');

        $this->defence()->install($this->facility, $card);
        $this->defence()->install($other, $card);

        $this->assertSame(2, $card->installations()->count());
    }

    /**
     * The worked example from rulebook 3.3.4.
     */
    public function test_moving_one_card_to_the_end_costs_one_credit(): void
    {
        $ids = $this->installAlphaBravoCharlie();

        $cost = $this->defence()->reorder($this->facility->fresh(), ProtectionKind::Physical, [
            $ids['Bravo'], $ids['Charlie'], $ids['Alpha'],
        ]);

        $this->assertSame(1, $cost);
        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $this->stackNames());
        $this->assertSame(49, $this->corporation->fresh()->credits);
    }

    /**
     * The second half of the same worked example: A, B, C to C, B, A moves both
     * A and C around B, so it costs 2.
     */
    public function test_reversing_three_cards_costs_two_credits(): void
    {
        $ids = $this->installAlphaBravoCharlie();

        $cost = $this->defence()->reorder($this->facility->fresh(), ProtectionKind::Physical, [
            $ids['Charlie'], $ids['Bravo'], $ids['Alpha'],
        ]);

        $this->assertSame(2, $cost);
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->stackNames());
        $this->assertSame(48, $this->corporation->fresh()->credits);
    }

    public function test_reordering_to_the_same_order_costs_nothing(): void
    {
        $ids = $this->installAlphaBravoCharlie();

        $cost = $this->defence()->reorder($this->facility->fresh(), ProtectionKind::Physical, [
            $ids['Alpha'], $ids['Bravo'], $ids['Charlie'],
        ]);

        $this->assertSame(0, $cost);
        $this->assertSame(50, $this->corporation->fresh()->credits);
    }

    public function test_swapping_two_neighbours_costs_one_credit(): void
    {
        $ids = $this->installAlphaBravoCharlie();

        $cost = $this->defence()->reorder($this->facility->fresh(), ProtectionKind::Physical, [
            $ids['Bravo'], $ids['Alpha'], $ids['Charlie'],
        ]);

        $this->assertSame(1, $cost);
    }

    public function test_a_reorder_must_name_the_whole_stack(): void
    {
        $ids = $this->installAlphaBravoCharlie();

        $this->expectException(ValidationException::class);

        $this->defence()->reorder($this->facility->fresh(), ProtectionKind::Physical, [
            $ids['Bravo'], $ids['Alpha'],
        ]);
    }

    public function test_a_corporation_that_cannot_pay_keeps_its_order(): void
    {
        $this->corporation->forceFill(['credits' => 0])->save();
        $ids = $this->installAlphaBravoCharlie();

        try {
            $this->defence()->reorder($this->facility->fresh(), ProtectionKind::Physical, [
                $ids['Charlie'], $ids['Bravo'], $ids['Alpha'],
            ]);
            $this->fail('Expected the reorder to be refused.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->stackNames());
    }

    public function test_the_first_removal_each_turn_is_free_and_the_rest_cost_a_credit(): void
    {
        app(TurnEngine::class)->start($this->game);
        $this->installAlphaBravoCharlie();

        $stack = $this->defence()->stack($this->facility, ProtectionKind::Physical);

        $this->assertSame(0, $this->defence()->remove($stack[0]));
        $this->assertSame(50, $this->corporation->fresh()->credits);

        $this->assertSame(1, $this->defence()->remove($stack[1]));
        $this->assertSame(49, $this->corporation->fresh()->credits);

        $this->assertSame(1, $this->defence()->remove($stack[2]));
        $this->assertSame(48, $this->corporation->fresh()->credits);
    }

    public function test_the_free_removal_comes_back_next_turn(): void
    {
        $engine = app(TurnEngine::class);
        $phase = $engine->start($this->game);
        $this->installAlphaBravoCharlie();

        $stack = $this->defence()->stack($this->facility, ProtectionKind::Physical);
        $this->defence()->remove($stack[0]);
        $this->assertSame(1, $this->defence()->remove($stack[1]));

        // Setup, Action, Team Time, then turn 2.
        $phase = $engine->advance($phase);
        $phase = $engine->advance($phase);
        $engine->advance($phase);

        $this->assertSame(2, $this->game->fresh()->currentTurn()?->number);
        $this->assertSame(0, $this->defence()->remove($stack[2]));
    }

    public function test_the_free_removal_is_per_facility(): void
    {
        app(TurnEngine::class)->start($this->game);

        $other = $this->facilityOfType(FacilityTypeBlueprint::RESEARCH, 'Wicker Post');
        $first = $this->defence()->install($this->facility, $this->card('Alpha'));
        $second = $this->defence()->install($other, $this->card('Bravo'));

        $this->assertSame(0, $this->defence()->remove($first));
        $this->assertSame(0, $this->defence()->remove($second));
    }

    public function test_removing_closes_the_gap_in_the_stack(): void
    {
        $ids = $this->installAlphaBravoCharlie();

        $middle = FacilityProtectionCard::query()->findOrFail($ids['Bravo']);

        $this->defence()->remove($middle);

        $stack = $this->defence()->stack($this->facility->fresh(), ProtectionKind::Physical);

        // The stack closes up around the gap: Alpha keeps the outermost slot
        // and Charlie moves in behind it.
        $this->assertSame([1, 2], $stack->pluck('position')->all());
        $this->assertSame(['Alpha', 'Charlie'], $this->stackNames());
    }

    public function test_control_can_install_a_card_over_http(): void
    {
        $card = $this->card('Alpha');

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['Alpha'], $this->stackNames());
    }

    public function test_control_can_reorder_a_stack_over_http(): void
    {
        $ids = $this->installAlphaBravoCharlie();

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/facilities/{$this->facility->id}/cards/order", [
                'kind' => 'physical',
                'order' => [
                    $ids['Bravo'],
                    $ids['Charlie'],
                    $ids['Alpha'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $this->stackNames());
        $this->assertSame(49, $this->corporation->fresh()->credits);
    }

    public function test_control_can_remove_a_card_over_http(): void
    {
        $card = $this->defence()->install($this->facility, $this->card('Alpha'));

        $this->actingAs($this->control())
            ->delete("/control/games/{$this->game->id}/facilities/{$this->facility->id}/cards/{$card->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([], $this->stackNames());
    }

    public function test_a_card_from_another_game_cannot_be_installed(): void
    {
        $other = ProtectionCardType::factory()->for(Game::factory())->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/facilities/{$this->facility->id}/cards", [
                'protection_card_type_id' => $other->id,
            ])
            ->assertSessionHasErrors('protection_card_type_id');
    }

    /**
     * @return array<string, int> card type ids, keyed by title
     */
    private function installAlphaBravoCharlie(): array
    {
        $ids = [];

        // Installed innermost first, so the stack reads Alpha, Bravo, Charlie
        // from the outside in - which is the worked example's A, B, C.
        foreach (['Charlie', 'Bravo', 'Alpha'] as $name) {
            $installed = $this->defence()->install($this->facility->fresh(), $this->card($name));

            // The installed card's id, not the catalogue card's: reorder names
            // the copies in this Facility.
            $ids[$name] = $installed->id;
        }

        return $ids;
    }
}
