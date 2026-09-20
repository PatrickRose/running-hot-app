<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which sections a player is offered.
 *
 * The sidebar used to draw every page the game has to everybody, so a Runner
 * was shown the Council and the research table - the first a room they hold no
 * seat in, the second a Corporation's own sub-game. A link nobody can use is
 * the application offering something it will not give.
 *
 * The boundary is read off the seats a user has claimed, so the cases worth
 * pinning are the ones where two seats disagree and the one where somebody
 * holds none at all.
 */
class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
    }

    /**
     * @return list<string>
     */
    private function sectionsFor(?User $user): array
    {
        return app(Navigation::class)->sectionsFor($this->game, $user);
    }

    private function seat(CharacterRole $role, array $attributes = []): User
    {
        $user = User::factory()->create();

        Character::factory()->create([
            ...$attributes,
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    public function test_a_runner_is_not_offered_the_council_or_the_research_table(): void
    {
        $sections = $this->sectionsFor($this->seat(CharacterRole::Runner));

        $this->assertNotContains('council', $sections);
        $this->assertNotContains('research', $sections);
    }

    public function test_a_runner_is_offered_their_runs_their_kit_and_the_market(): void
    {
        $sections = $this->sectionsFor($this->seat(CharacterRole::Runner));

        $this->assertContains('dashboard', $sections);
        $this->assertContains('facilities', $sections);
        $this->assertContains('runs', $sections);
        $this->assertContains('equipment', $sections);
        $this->assertContains('shop', $sections);
    }

    /**
     * 3.4 hands the Facility game to a side rather than to one role, so a
     * Freelancer reads exactly as a Runner does.
     */
    public function test_a_freelancer_reads_as_a_runner_does(): void
    {
        $this->assertSame(
            $this->sectionsFor($this->seat(CharacterRole::Runner)),
            $this->sectionsFor($this->seat(CharacterRole::Freelancer)),
        );
    }

    public function test_a_security_player_gets_the_research_table_but_not_a_kit(): void
    {
        $corporation = Corporation::factory()->create(['game_id' => $this->game->id]);

        $sections = $this->sectionsFor($this->seat(
            CharacterRole::Security,
            ['corporation_id' => $corporation->id],
        ));

        // 3.4.2 keeps a Facility's contents Secret from outside the
        // Corporation rather than from inside it, so every Corporate seat
        // reads the table.
        $this->assertContains('research', $sections);
        $this->assertContains('runs', $sections);
        $this->assertContains('shop', $sections);

        // A Corporate seat is refused Equipment outright.
        $this->assertNotContains('equipment', $sections);
    }

    /**
     * A CEO votes with their Corporation's Political Will, so the seat comes
     * with the role rather than from a column.
     */
    public function test_a_ceo_is_offered_the_council(): void
    {
        $corporation = Corporation::factory()->create(['game_id' => $this->game->id]);

        $sections = $this->sectionsFor($this->seat(
            CharacterRole::Ceo,
            ['corporation_id' => $corporation->id],
        ));

        $this->assertContains('council', $sections);
    }

    /**
     * Everybody else at the Council votes with a bloc Control wrote on them -
     * HM Government's six, the Runner Representative of 3.1.3's own agenda
     * card. Null means no seat, which is almost everybody.
     */
    public function test_a_character_control_has_seated_is_offered_the_council(): void
    {
        $seated = $this->seat(CharacterRole::Other, ['council_votes' => 6]);
        $unseated = $this->seat(CharacterRole::Other);

        $this->assertContains('council', $this->sectionsFor($seated));
        $this->assertNotContains('council', $this->sectionsFor($unseated));
    }

    /**
     * A user holds characters rather than a side, so somebody running a
     * Freelancer and sitting in a Security chair gets both sets.
     */
    public function test_two_seats_add_together(): void
    {
        $user = User::factory()->create();
        $corporation = Corporation::factory()->create(['game_id' => $this->game->id]);

        Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => CharacterRole::Freelancer,
        ]);

        Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => CharacterRole::Research,
            'corporation_id' => $corporation->id,
        ]);

        $sections = $this->sectionsFor($user);

        $this->assertContains('equipment', $sections);
        $this->assertContains('research', $sections);
    }

    public function test_somebody_holding_no_seat_gets_only_the_public_pages(): void
    {
        $sections = $this->sectionsFor(User::factory()->create());

        $this->assertSame(['dashboard', 'facilities'], $sections);
    }

    /**
     * A ruling mid-game must never wait on Control holding the right seat to
     * reach the page.
     */
    public function test_control_is_offered_everything(): void
    {
        $user = User::factory()->create();

        ControlMember::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
        ]);

        $this->assertSame(Navigation::SECTIONS, $this->sectionsFor($user));
    }

    public function test_a_guest_is_offered_nothing(): void
    {
        $this->assertSame([], $this->sectionsFor(null));
    }

    public function test_the_sidebar_is_told_which_sections_to_draw(): void
    {
        $user = $this->seat(CharacterRole::Runner);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('nav', ['dashboard', 'facilities', 'runs', 'equipment', 'shop']));
    }
}
