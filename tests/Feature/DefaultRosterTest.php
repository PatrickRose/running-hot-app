<?php

namespace Tests\Feature;

use App\Actions\CreateDefaultRoster;
use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The roster of the real game, as configured in config/running_hot.php.
 *
 * These assertions are deliberately literal: the point of the feature is that
 * the numbers match the briefing documents, so a test that recomputed them from
 * the config would prove nothing.
 */
class DefaultRosterTest extends TestCase
{
    use RefreshDatabase;

    private function roster(): CreateDefaultRoster
    {
        return app(CreateDefaultRoster::class);
    }

    public function test_it_creates_every_corporation_with_its_starting_trackers(): void
    {
        $game = Game::factory()->create();

        $this->roster()->handle($game);

        $expected = [
            'Augmented Nucleotech' => ['income' => 5, 'political_will' => 5, 'credits' => 40],
            'Digital Tactical Control' => ['income' => 13, 'political_will' => 7, 'credits' => 27],
            'Genetic Equity' => ['income' => 10, 'political_will' => 10, 'credits' => 10],
            'Gordon' => ['income' => 13, 'political_will' => 7, 'credits' => 22],
            'McCullough Calibrated Mechanical' => ['income' => 12, 'political_will' => 9, 'credits' => 20],
        ];

        $this->assertSame(count($expected), $game->corporations()->count());

        foreach ($expected as $name => $trackers) {
            $this->assertDatabaseHas('corporations', [
                'game_id' => $game->id,
                'name' => $name,
                ...$trackers,
            ]);
        }
    }

    public function test_every_corporation_fields_a_ceo_a_security_and_a_research_player(): void
    {
        $game = Game::factory()->create();

        $this->roster()->handle($game);

        foreach ($game->corporations as $corporation) {
            $roles = $corporation->characters()
                ->pluck('role')
                ->map(fn (CharacterRole $role): string => $role->value)
                ->sort()
                ->values()
                ->all();

            $this->assertSame(['ceo', 'research', 'security'], $roles, $corporation->name);
        }

        $this->assertDatabaseHas('characters', [
            'game_id' => $game->id,
            'name' => 'Gordon Corp CEO',
            'role' => 'ceo',
        ]);
    }

    public function test_it_creates_every_gang_with_its_runners(): void
    {
        $game = Game::factory()->create();

        $this->roster()->handle($game);

        $expected = [
            'Facers' => 5,
            'g33ks' => 5,
            'Dancers' => 6,
            'Gruffsters' => 4,
        ];

        $this->assertSame(count($expected), $game->gangs()->count());

        foreach ($expected as $name => $runners) {
            $gang = Gang::query()->where('game_id', $game->id)->where('name', $name)->sole();

            $this->assertSame(0, $gang->notoriety, $name);
            $this->assertSame($runners, $gang->characters()->count(), $name);
        }
    }

    public function test_runner_skills_come_from_the_character_sheet(): void
    {
        $game = Game::factory()->create();

        $this->roster()->handle($game);

        $expected = [
            'Con' => ['brawn' => 3, 'hack' => 3, 'charisma' => 5, 'body' => 4],
            '$TUX' => ['brawn' => 3, 'hack' => 0, 'charisma' => 2, 'body' => 6],
            'Ballet' => ['brawn' => 1, 'hack' => 1, 'charisma' => 6, 'body' => 3],
            'Scorer' => ['brawn' => 3, 'hack' => 3, 'charisma' => 3, 'body' => 3],
            'Yale Pirit' => ['brawn' => 5, 'hack' => 2, 'charisma' => 3, 'body' => 6],
        ];

        foreach ($expected as $name => $skills) {
            $this->assertDatabaseHas('characters', [
                'game_id' => $game->id,
                'name' => $name,
                ...$skills,
            ]);
        }
    }

    public function test_freelancers_the_press_and_the_government_belong_to_no_team(): void
    {
        $game = Game::factory()->create();

        $this->roster()->handle($game);

        $expected = [
            'Jack Scanton' => CharacterRole::Freelancer,
            'Mandel Reso' => CharacterRole::Freelancer,
            'Yale Pirit' => CharacterRole::Freelancer,
            'Business Times' => CharacterRole::Press,
            'Th3 Undergr0und' => CharacterRole::Press,
            'HM Government' => CharacterRole::Other,
        ];

        foreach ($expected as $name => $role) {
            $character = Character::query()->where('game_id', $game->id)->where('name', $name)->sole();

            $this->assertSame($role, $character->role, $name);
            $this->assertNull($character->corporation_id, $name);
            $this->assertNull($character->gang_id, $name);
        }
    }

    /**
     * Corporate players spend their Corporation's Credits, not their own.
     */
    public function test_only_non_corporate_characters_start_with_credits(): void
    {
        $game = Game::factory()->create();

        $this->roster()->handle($game);

        foreach ($game->characters as $character) {
            $this->assertSame(
                $character->role->isCorporate() ? 0 : 5,
                $character->credits,
                $character->name,
            );
        }
    }

    public function test_it_creates_the_whole_roster(): void
    {
        $game = Game::factory()->create();

        $created = $this->roster()->handle($game);

        $this->assertFalse($created['skipped']);
        $this->assertSame(5, $created['corporations']);
        $this->assertSame(4, $created['gangs']);
        $this->assertSame(41, $created['characters']);
        $this->assertSame(41, $game->characters()->count());
    }

    /**
     * A second application would collide with the unique index on a team name,
     * so a game that already has teams is left alone.
     */
    /**
     * The roster configuration carries entries the Corporation model has no
     * column for - its starting Facilities, and the Protection Cards its
     * briefing gives it - and they have to be stripped rather than left for
     * mass assignment to drop.
     *
     * Artisan seeds with mass assignment turned off (SeedCommand wraps the run
     * in Model::unguarded), so a stray key reaches the insert and takes the
     * whole seeder down instead of being ignored. Which is exactly how it was
     * found.
     */
    public function test_the_roster_seeds_with_mass_assignment_turned_off(): void
    {
        $game = Game::factory()->create();

        Model::unguarded(function () use ($game): void {
            $this->roster()->handle($game);
        });

        $this->assertSame(
            count(config('running_hot.corporations')),
            $game->corporations()->count(),
        );
    }

    public function test_it_leaves_a_game_that_already_has_teams_alone(): void
    {
        $game = Game::factory()->create();
        Corporation::factory()->create(['game_id' => $game->id, 'name' => 'Someone Else']);

        $created = $this->roster()->handle($game);

        $this->assertTrue($created['skipped']);
        $this->assertSame(1, $game->corporations()->count());
        $this->assertSame(0, $game->gangs()->count());
    }

    public function test_creating_a_game_sets_up_the_roster(): void
    {
        $this->actingAs(User::factory()->control()->create())
            ->post('/control/games', [
                'name' => 'Running Hot — Sheffield',
                'discord_webhook_url' => 'https://discord.com/api/webhooks/123456789/abcdef-ghij',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $game = Game::query()->where('name', 'Running Hot — Sheffield')->sole();

        $this->assertSame(5, $game->corporations()->count());
        $this->assertSame(4, $game->gangs()->count());
        $this->assertSame(41, $game->characters()->count());
    }

    /**
     * Provisioning a game's Discord server permissions its team channels from
     * the roster, so the roster has to be built before Control is handed over
     * to that flow rather than after it.
     */
    public function test_the_roster_is_built_before_handing_over_to_discord(): void
    {
        $this->actingAs(User::factory()->control()->create())
            ->post('/control/games', [
                'name' => 'Straight To Discord',
                'connect_discord' => '1',
            ])
            ->assertRedirectContains('/discord/connect')
            ->assertSessionHasNoErrors();

        $game = Game::query()->where('name', 'Straight To Discord')->sole();

        $this->assertSame(5, $game->corporations()->count());
        $this->assertSame(4, $game->gangs()->count());
        $this->assertSame(41, $game->characters()->count());
    }

    public function test_control_can_ask_for_an_empty_game(): void
    {
        $this->actingAs(User::factory()->control()->create())
            ->post('/control/games', [
                'name' => 'Blank Slate',
                'skip_default_roster' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $game = Game::query()->where('name', 'Blank Slate')->sole();

        $this->assertSame(0, $game->corporations()->count());
        $this->assertSame(0, $game->gangs()->count());
        $this->assertSame(0, $game->characters()->count());
    }
}
