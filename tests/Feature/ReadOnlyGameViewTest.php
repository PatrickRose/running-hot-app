<?php

namespace Tests\Feature;

use App\Actions\CreateDefaultFacilities;
use App\Actions\CreateDefaultRoster;
use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\AgendaCard;
use App\Models\Character;
use App\Models\Facility;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\User;
use App\Services\TurnEngine;
use App\Support\GamePresenter;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A game off the clock is read, not played.
 *
 * Players used to see nothing at all either side of the evening: every
 * player-facing page hangs off Game::current(), which answered only for a
 * running game, so somebody reading their briefing the morning before a session
 * and somebody looking back at how last night went were both told "no game is
 * running". The roster, the Facilities and the starting kits are all seeded
 * when the game is created, so there was plenty to read and no way to reach it.
 *
 * What is worth pinning is the pair of boundaries that makes this safe: the
 * pages come up, and every act in them is still refused. The second half was
 * already true - each policy asks about the clock in its own right - so these
 * tests are what stop it quietly becoming untrue.
 */
class ReadOnlyGameViewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A game with a roster and Facilities, in whatever state it is wanted in.
     */
    private function game(GameStatus $status): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Running]);

        app(CreateDefaultRoster::class)->handle($game);
        app(CreateDefaultFacilities::class)->handle($game);

        // A Finished game is started and then finished through the engine, so
        // it carries the turns and the completed phase a real one would be read
        // back through - a status written straight onto the row would leave a
        // phase still running underneath it, which is not a state the game
        // reaches. A Draft one has never been started at all.
        if ($status === GameStatus::Finished) {
            $engine = app(TurnEngine::class);
            $engine->start($game);
            $engine->finish($game);
        }

        if ($status === GameStatus::Draft) {
            $game->forceFill(['status' => GameStatus::Draft])->save();
        }

        return $game->refresh();
    }

    /**
     * The player holding one of a Corporation's seats.
     */
    private function seatIn(Game $game, string $corporation, CharacterRole $role): User
    {
        $user = User::factory()->create();

        $character = $game->characters()
            ->whereHas('corporation', fn ($query) => $query->where('name', $corporation))
            ->where('role', $role)
            ->sole();

        $character->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    /**
     * @return array<int, GameStatus>
     */
    public static function offTheClock(): array
    {
        return [
            'not started' => [GameStatus::Draft],
            'finished' => [GameStatus::Finished],
        ];
    }

    public function test_a_running_game_wins_however_new_the_draft_beside_it_is(): void
    {
        $running = Game::factory()->create(['status' => GameStatus::Running]);
        $next = Game::factory()->create(['status' => GameStatus::Draft]);

        $this->assertGreaterThan($running->id, $next->id);
        $this->assertSame($running->id, Game::current()?->id);
    }

    public function test_the_newest_game_answers_when_none_is_running(): void
    {
        $finished = Game::factory()->create(['status' => GameStatus::Finished]);
        $next = Game::factory()->create(['status' => GameStatus::Draft]);

        $this->assertNotSame($finished->id, Game::current()?->id);
        $this->assertSame($next->id, Game::current()?->id);
    }

    public function test_no_game_at_all_is_still_nothing(): void
    {
        $this->assertNull(Game::current());
    }

    #[DataProvider('offTheClock')]
    public function test_a_player_reaches_their_pages(GameStatus $status): void
    {
        $game = $this->game($status);
        $user = $this->seatIn($game, 'Gordon', CharacterRole::Security);

        foreach (['/facilities', '/equipment', '/research', '/shop', '/runs'] as $page) {
            $this->actingAs($user)->get($page)->assertOk();
        }
    }

    #[DataProvider('offTheClock')]
    public function test_the_facility_board_still_carries_a_corporations_own_stacks(GameStatus $status): void
    {
        $game = $this->game($status);
        $user = $this->seatIn($game, 'Gordon', CharacterRole::Security);

        $board = app(GamePresenter::class)->facilityBoard($game, $user);

        $this->assertNotNull($board['own'], 'A Corporate seat should still read its own Facilities.');
        $this->assertSame('Gordon', $board['own']['name']);
        $this->assertNotEmpty($board['own']['facilities']);
    }

    /**
     * The whole point of the tier: the page opens and the board is read-only.
     */
    #[DataProvider('offTheClock')]
    public function test_security_may_read_their_stacks_and_not_arrange_them(GameStatus $status): void
    {
        $game = $this->game($status);
        $user = $this->seatIn($game, 'Gordon', CharacterRole::Security);

        $board = app(GamePresenter::class)->facilityBoard($game, $user);

        $this->assertFalse($board['own']['can_defend']);
    }

    #[DataProvider('offTheClock')]
    public function test_installing_a_card_is_refused(GameStatus $status): void
    {
        $game = $this->game($status);
        $user = $this->seatIn($game, 'Gordon', CharacterRole::Security);

        /** @var Facility $facility */
        $facility = $game->facilities()
            ->whereHas('corporation', fn ($query) => $query->where('name', 'Gordon'))
            ->firstOrFail();

        /** @var ProtectionCardType $card */
        $card = ProtectionCardType::query()->where('game_id', $game->id)->firstOrFail();

        $this->actingAs($user)
            ->post("/facilities/{$facility->id}/cards", [
                'protection_card_type_id' => $card->id,
            ])
            ->assertForbidden();
    }

    /**
     * A seat is a fact about the roster, so the Chamber opens; voting is an
     * act, so it does not.
     */
    #[DataProvider('offTheClock')]
    public function test_a_ceo_may_read_the_chamber_and_not_vote(GameStatus $status): void
    {
        $game = $this->game($status);
        $user = $this->seatIn($game, 'Gordon', CharacterRole::Ceo);

        $this->actingAs($user)->get('/council')->assertOk();

        $this->assertFalse(
            $user->can('create', [AgendaCard::class, $game]),
            'A custom agenda is an act, and acts wait for the clock.',
        );
    }

    #[DataProvider('offTheClock')]
    public function test_somebody_with_no_council_seat_is_still_kept_out(GameStatus $status): void
    {
        $game = $this->game($status);
        $user = $this->seatIn($game, 'Gordon', CharacterRole::Security);

        $this->actingAs($user)->get('/council')->assertForbidden();
    }

    #[DataProvider('offTheClock')]
    public function test_the_shop_counter_is_closed(GameStatus $status): void
    {
        $game = $this->game($status);
        $user = $this->seatIn($game, 'Gordon', CharacterRole::Security);

        $this->actingAs($user)
            ->get('/shop')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('shop.open', false));
    }

    #[DataProvider('offTheClock')]
    public function test_the_dashboard_still_names_the_seats_somebody_holds(GameStatus $status): void
    {
        $game = $this->game($status);
        $user = $this->seatIn($game, 'Gordon', CharacterRole::Security);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('characters', 1)
                ->where('game.status', $status->value));
    }

    #[DataProvider('offTheClock')]
    public function test_the_sidebar_still_offers_the_sections_a_seat_earns(GameStatus $status): void
    {
        $game = $this->game($status);
        $user = $this->seatIn($game, 'Gordon', CharacterRole::Ceo);

        // Equipment among them: a hand is read by anybody holding a seat, a
        // CEO included, since 2.1 has Runners buying equipment "from other
        // players" and the card may be sitting with whoever bought it to hand
        // over. Reading one is the thing a game off the clock still allows;
        // handing it on is the act CharacterPolicy::giveEquipment refuses.
        $this->assertSame(
            ['dashboard', 'facilities', 'runs', 'equipment', 'council', 'research', 'shop', 'dice'],
            app(Navigation::class)->sectionsFor($game, $user),
        );
    }

    /**
     * A Runner reading ahead: the public Facility list is what a target is
     * chosen off, and it is posted in a channel the whole game reads anyway.
     */
    public function test_a_runner_reads_the_public_list_before_the_game_starts(): void
    {
        $game = $this->game(GameStatus::Draft);

        $user = User::factory()->create();

        /** @var Character $runner */
        $runner = $game->characters()->where('role', CharacterRole::Runner)->firstOrFail();
        $runner->forceFill(['user_id' => $user->id])->save();

        $board = app(GamePresenter::class)->facilityBoard($game, $user);

        $this->assertNull($board['own'], 'A Runner holds no Corporation to read.');
        $this->assertNotEmpty($board['public']);
    }

    /**
     * Control loses nothing by the game being off the clock, and gains no new
     * way to act either: this is the same override it always had.
     */
    public function test_control_still_sees_everything(): void
    {
        $game = $this->game(GameStatus::Finished);

        $user = User::factory()->create(['is_control' => true]);

        $this->assertSame(
            Navigation::SECTIONS,
            app(Navigation::class)->sectionsFor($game, $user),
        );
    }
}
