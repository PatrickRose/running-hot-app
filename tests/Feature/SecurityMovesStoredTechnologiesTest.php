<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\TechnologyHoldingStatus;
use App\Enums\TechnologyOrigin;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Models\User;
use App\Services\TurnEngine;
use App\Support\FacilityTypeBlueprint;
use App\Support\GamePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Moving a technology card between a Corporation's own Facilities
 * (rulebook 3.2.2).
 *
 * The rulebook has a Research player place a technology when they research it
 * and then says nothing about moving it afterwards, because at the table the
 * cards are in front of you and you pick one up. The application had no gesture
 * for that at all: the row could only be moved by Control writing a PATCH by
 * hand.
 *
 * It is Security's for the reason the stacks are - which Facility holds a
 * technology is a decision about what a Run would come away with - and what
 * these tests hold onto is that boundary and the rules behind it. Storage is
 * still 2 per Corporate Facility, a card that names a Facility type still has
 * to be housed in one, and a card a Run took is still not in the building to be
 * moved.
 */
class SecurityMovesStoredTechnologiesTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $corporation;

    private Facility $from;

    private Facility $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

        $this->corporation = Corporation::factory()->for($this->game)->create([
            'name' => 'Gordon',
            'credits' => 50,
        ]);

        // Storage is 2 for every Corporate Facility the Corporation owns, so
        // without one of these every Facility in the game holds nothing at all.
        $this->from = $this->facility(FacilityTypeBlueprint::CORPORATE, 'Owlerton Works');
        $this->to = $this->facility(FacilityTypeBlueprint::CORPORATE, 'Kelham Island');
    }

    private function facilityType(string $key): FacilityType
    {
        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', $key)->sole();

        return $type;
    }

    private function facility(string $key, string $name): Facility
    {
        return Facility::factory()
            ->for($this->corporation)
            ->for($this->facilityType($key))
            ->create(['name' => $name]);
    }

    /**
     * A player sitting in one of a Corporation's seats.
     */
    private function seat(Corporation $corporation, CharacterRole $role): User
    {
        $user = User::factory()->create();

        $corporation->characters()->create([
            'game_id' => $this->game->id,
            'name' => $corporation->name.' '.$role->label(),
            'role' => $role,
        ])->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function security(): User
    {
        return $this->seat($this->corporation, CharacterRole::Security);
    }

    /**
     * A technology stored in a Facility.
     */
    private function stored(
        Facility $facility,
        string $name = 'Power (Part 1/4)',
        ?FacilityType $requires = null,
    ): TechnologyHolding {
        /** @var TechnologyType $type */
        $type = TechnologyType::factory()->for($this->game)->create([
            'name' => $name,
            'required_facility_type_id' => $requires?->id,
        ]);

        return TechnologyHolding::factory()
            ->for($this->game)
            ->for($this->corporation)
            ->for($type, 'technologyType')
            ->create(['facility_id' => $facility->id]);
    }

    private function move(User $as, Facility $to, TechnologyHolding $holding): TestResponse
    {
        return $this->actingAs($as)
            ->patch("/facilities/{$to->id}/technologies/{$holding->id}");
    }

    // -- Moving one ---------------------------------------------------------

    public function test_security_moves_a_technology_to_another_of_their_facilities(): void
    {
        $holding = $this->stored($this->from);

        $this->move($this->security(), $this->to, $holding)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($this->to->id, $holding->refresh()->facility_id);
    }

    /**
     * Storage is what constrains this, and it is the reason the gesture exists:
     * a Facility that is full is why the next technology cannot be researched.
     */
    public function test_a_facility_already_storing_its_technologies_is_refused(): void
    {
        $capacity = 2 * 2;

        for ($i = 0; $i < $capacity; $i++) {
            $this->stored($this->to, 'Filler '.$i);
        }

        $holding = $this->stored($this->from);

        $this->move($this->security(), $this->to, $holding)
            ->assertSessionHasErrors('facility_id');

        $this->assertSame($this->from->id, $holding->refresh()->facility_id);
    }

    /**
     * A technology that names a Facility type has to be housed in one
     * (rulebook 3.2.2), wherever it is being moved from.
     */
    public function test_a_technology_that_names_a_facility_type_may_only_go_in_one(): void
    {
        $research = $this->facility(FacilityTypeBlueprint::RESEARCH, 'Attercliffe Yard');
        $holding = $this->stored(
            $research,
            'Gene Splicing',
            requires: $this->facilityType(FacilityTypeBlueprint::RESEARCH),
        );

        $this->move($this->security(), $this->to, $holding)
            ->assertSessionHasErrors('facility_id');

        $this->assertSame($research->id, $holding->refresh()->facility_id);
    }

    /**
     * A Facility is not yours until it opens, which is the same reading
     * researching into one already had.
     */
    public function test_a_facility_still_being_built_stores_nothing_yet(): void
    {
        $building = $this->facility(FacilityTypeBlueprint::CORPORATE, 'Hillsborough Annexe');
        $building->forceFill([
            'available_from_turn' => ($this->game->currentTurn()?->number ?? 1) + 1,
        ])->save();

        $holding = $this->stored($this->from);

        $this->move($this->security(), $building, $holding)
            ->assertSessionHasErrors('facility_id');

        $this->assertSame($this->from->id, $holding->refresh()->facility_id);
    }

    /**
     * A Run that stole or destroyed a card took it out of the building as well
     * as off the Corporation, so there is nothing in there to move. Putting it
     * back is Control's judgement and Control's Restore, not a quiet drag.
     */
    public function test_a_card_a_run_took_is_not_in_the_building_to_be_moved(): void
    {
        $holding = $this->stored($this->from);
        $holding->forceFill(['status' => TechnologyHoldingStatus::Stolen])->save();

        $this->move($this->security(), $this->to, $holding)
            ->assertSessionHasErrors('facility_id');

        $this->assertSame($this->from->id, $holding->refresh()->facility_id);
    }

    /**
     * A copy nobody has paid for takes up a slot (footnote 8 to 3.2.6), so
     * moving one is exactly how a Corporation frees the slot it needs.
     */
    public function test_a_claimed_copy_moves_like_anything_else(): void
    {
        /** @var TechnologyType $type */
        $type = TechnologyType::factory()->for($this->game)->create(['name' => 'Internet Link']);

        $holding = TechnologyHolding::factory()
            ->for($this->game)
            ->for($this->corporation)
            ->for($type, 'technologyType')
            ->claimed(TechnologyOrigin::GoodCopy)
            ->create(['facility_id' => $this->from->id]);

        $this->move($this->security(), $this->to, $holding)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($this->to->id, $holding->refresh()->facility_id);
        // And it is still a copy: moving it does not pay for it.
        $this->assertSame(TechnologyHoldingStatus::Claimed, $holding->status);
    }

    public function test_dropping_a_card_back_where_it_was_changes_nothing(): void
    {
        $holding = $this->stored($this->from);

        $this->move($this->security(), $this->from, $holding)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($this->from->id, $holding->refresh()->facility_id);
    }

    // -- Who may do it ------------------------------------------------------

    /**
     * The CEO and the Research player read these cards - 3.4.2 keeps a
     * Facility's contents Secret from outside the Corporation, not from inside
     * it - and may not move them, for the reason they may not move the stacks.
     */
    public function test_another_corporate_seat_reads_the_cards_but_cannot_move_them(): void
    {
        $holding = $this->stored($this->from);
        $ceo = $this->seat($this->corporation, CharacterRole::Ceo);

        $board = app(GamePresenter::class)->facilityBoard($this->game, $ceo);

        $this->assertNotNull($board['own']);
        $this->assertFalse($board['own']['can_defend']);
        $facility = collect($board['own']['facilities'])->firstWhere('id', $this->from->id);

        $this->assertNotNull($facility);
        $this->assertContains(
            'Power (Part 1/4)',
            array_column($facility['technologies'], 'name'),
        );

        $this->move($ceo, $this->to, $holding)->assertForbidden();

        $this->assertSame($this->from->id, $holding->refresh()->facility_id);
    }

    public function test_a_rival_corporations_security_cannot_move_this_card(): void
    {
        $rival = Corporation::factory()->for($this->game)->create(['name' => 'ANT']);
        $holding = $this->stored($this->from);

        $this->move($this->seat($rival, CharacterRole::Security), $this->to, $holding)
            ->assertForbidden();

        $this->assertSame($this->from->id, $holding->refresh()->facility_id);
    }

    /**
     * The policy only asks whether the Facility is yours, so without the
     * controller's own check a Security player could pull a rival's technology
     * across into their own building.
     */
    public function test_a_rivals_technology_cannot_be_pulled_into_your_own_facility(): void
    {
        $rival = Corporation::factory()->for($this->game)->create(['name' => 'ANT']);

        /** @var FacilityType $corporate */
        $corporate = $this->facilityType(FacilityTypeBlueprint::CORPORATE);

        $theirs = Facility::factory()
            ->for($rival)
            ->for($corporate)
            ->create(['name' => 'Their Works']);

        /** @var TechnologyType $type */
        $type = TechnologyType::factory()->for($this->game)->create(['name' => 'Their Secret']);

        $holding = TechnologyHolding::factory()
            ->for($this->game)
            ->for($rival)
            ->for($type, 'technologyType')
            ->create(['facility_id' => $theirs->id]);

        $this->move($this->security(), $this->to, $holding)->assertNotFound();

        $this->assertSame($theirs->id, $holding->refresh()->facility_id);
    }

    /**
     * Control reaches this through the policy's before(), because a ruling
     * mid-game must not wait on the Security player being at their laptop.
     */
    public function test_control_may_move_a_card_without_holding_a_seat(): void
    {
        $holding = $this->stored($this->from);

        $user = User::factory()->create();
        ControlMember::factory()->for($this->game)->create()
            ->forceFill(['user_id' => $user->id])->save();

        $this->move($user, $this->to, $holding)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($this->to->id, $holding->refresh()->facility_id);
    }

    // -- What the board is told ---------------------------------------------

    /**
     * A card a Run stole or destroyed keeps its row, but it is not in the
     * building any more - so listing it made the count on screen disagree with
     * the capacity the server enforces.
     */
    public function test_a_card_out_of_the_building_is_not_listed_in_it(): void
    {
        $standing = $this->stored($this->from, 'Power (Part 1/4)');

        $gone = $this->stored($this->from, 'Power (Part 2/4)');
        $gone->forceFill(['status' => TechnologyHoldingStatus::Destroyed])->save();

        $board = app(GamePresenter::class)->facilityBoard($this->game, $this->security());

        $this->assertNotNull($board['own']);

        $facility = collect($board['own']['facilities'])
            ->firstWhere('id', $this->from->id);

        $this->assertNotNull($facility);
        $this->assertSame(
            [$standing->technologyType->name],
            array_column($facility['technologies'], 'name'),
        );
    }
}
