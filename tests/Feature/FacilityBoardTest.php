<?php

namespace Tests\Feature;

use App\Actions\CreateDefaultFacilities;
use App\Actions\CreateDefaultRoster;
use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\User;
use App\Support\FactionBadge;
use App\Support\GamePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a player may see of the Facilities.
 *
 * A Corporate player sees their own Corporation's defences in full; everyone
 * sees the public list of who owns what. Rulebook 3.4.2 makes the number of
 * Protection Cards in a Facility Secret, so the boundary between those two
 * tiers is the thing worth testing.
 */
class FacilityBoardTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        app(CreateDefaultRoster::class)->handle($this->game);
        app(CreateDefaultFacilities::class)->handle($this->game);
    }

    private function corporation(string $name): Corporation
    {
        return $this->game->corporations()->where('name', $name)->sole();
    }

    /**
     * A player holding one of a Corporation's seats.
     */
    private function playerFor(string $corporationName, CharacterRole $role): User
    {
        $user = User::factory()->create();

        $character = $this->game->characters()
            ->where('corporation_id', $this->corporation($corporationName)->id)
            ->where('role', $role)
            ->sole();

        $character->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function runner(): User
    {
        $user = User::factory()->create();

        /** @var Character $character */
        $character = $this->game->characters()
            ->where('role', CharacterRole::Runner)
            ->firstOrFail();

        $character->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function boardFor(?User $user): array
    {
        return app(GamePresenter::class)->facilityBoard($this->game, $user);
    }

    public function test_a_security_player_sees_their_own_stacks(): void
    {
        $board = $this->boardFor($this->playerFor('Gordon', CharacterRole::Security));

        $this->assertNotNull($board['own']);
        $this->assertSame('Gordon', $board['own']['name']);
        $this->assertNotEmpty($board['own']['facilities']);

        $facility = $board['own']['facilities'][0];

        $this->assertArrayHasKey('stacks', $facility);
        $this->assertNotEmpty($facility['stacks'][0]['cards']);
    }

    public function test_a_ceo_and_a_researcher_see_their_corporation_too(): void
    {
        foreach ([CharacterRole::Ceo, CharacterRole::Research] as $role) {
            $board = $this->boardFor($this->playerFor('Genetic Equity', $role));

            $this->assertNotNull($board['own'], $role->value.' should see their Corporation.');
            $this->assertSame('Genetic Equity', $board['own']['name']);
        }
    }

    public function test_a_corporate_player_sees_their_own_derived_numbers(): void
    {
        $board = $this->boardFor($this->playerFor('Digital Tactical Control', CharacterRole::Security));

        // DTC opens with two Security Facilities: 3 + 2 physical, 3 + 4 cyber.
        $this->assertSame(5, $board['own']['physical_slots']);
        $this->assertSame(7, $board['own']['cyber_slots']);
    }

    /**
     * The rule this whole split exists for.
     */
    public function test_a_security_player_cannot_read_a_rivals_stacks(): void
    {
        $board = $this->boardFor($this->playerFor('Gordon', CharacterRole::Security));

        $rivals = collect($board['public'])->reject(fn (array $row): bool => $row['name'] === 'Gordon');

        $this->assertGreaterThan(0, $rivals->count());

        foreach ($rivals as $rival) {
            foreach ($rival['facilities'] as $facility) {
                $this->assertArrayNotHasKey('stacks', $facility);
                $this->assertArrayNotHasKey('security', $facility);
            }
        }
    }

    public function test_a_runner_sees_the_public_list_and_no_defences(): void
    {
        $board = $this->boardFor($this->runner());

        $this->assertNull($board['own']);
        $this->assertCount($this->game->corporations()->count(), $board['public']);

        foreach ($board['public'] as $row) {
            $this->assertFalse($row['is_yours']);

            foreach ($row['facilities'] as $facility) {
                $this->assertArrayNotHasKey('stacks', $facility);
            }
        }
    }

    public function test_the_public_list_never_carries_a_card_title(): void
    {
        $board = $this->boardFor($this->runner());
        $json = json_encode($board['public']);

        $this->assertIsString($json);
        $this->assertGreaterThan(0, $this->game->protectionCardTypes()->count());

        foreach ($this->game->protectionCardTypes as $card) {
            $this->assertStringNotContainsString($card->name, $json);
        }
    }

    public function test_the_public_list_names_every_facility_and_its_type(): void
    {
        $board = $this->boardFor($this->runner());

        $gordon = collect($board['public'])->firstWhere('name', 'Gordon');

        $this->assertNotNull($gordon);
        $this->assertSame(
            $this->corporation('Gordon')->facilities()->count(),
            count($gordon['facilities']),
        );
        $this->assertContains('Gordon Tower', array_column($gordon['facilities'], 'name'));
    }

    public function test_a_corporate_player_is_told_which_corporation_is_theirs(): void
    {
        $board = $this->boardFor($this->playerFor('Gordon', CharacterRole::Security));

        $mine = collect($board['public'])->where('is_yours', true)->pluck('name')->all();

        $this->assertSame(['Gordon'], $mine);
    }

    /**
     * Every faction in a payload carries the same three fields, so the browser
     * takes one shape everywhere instead of one per page. logo_path is null on
     * a checkout with no artwork, which is the normal case and not an error.
     */
    public function test_every_corporation_carries_its_badge(): void
    {
        $board = $this->boardFor($this->playerFor('Gordon', CharacterRole::Security));

        foreach ($board['public'] as $corporation) {
            $this->assertArrayHasKey('logo_path', $corporation);
            $this->assertSame(
                FactionBadge::cssColour($corporation['name']),
                $corporation['colour'],
            );
        }

        $this->assertNotNull($board['own']);
        $this->assertSame('Gordon', $board['own']['name']);
        $this->assertSame(FactionBadge::cssColour('Gordon'), $board['own']['colour']);
        $this->assertArrayHasKey('logo_path', $board['own']);
    }

    public function test_a_player_with_no_character_still_sees_the_public_list(): void
    {
        $board = $this->boardFor(User::factory()->create());

        $this->assertNull($board['own']);
        $this->assertNotEmpty($board['public']);
    }

    public function test_a_player_can_open_the_page(): void
    {
        $this->actingAs($this->runner())
            ->get('/facilities')
            ->assertOk();
    }

    public function test_a_guest_cannot(): void
    {
        $this->get('/facilities')->assertRedirect('/login');
    }

    public function test_the_page_copes_with_no_running_game(): void
    {
        $this->game->forceFill(['status' => GameStatus::Finished])->save();

        $this->actingAs(User::factory()->create())
            ->get('/facilities')
            ->assertOk();
    }
}
