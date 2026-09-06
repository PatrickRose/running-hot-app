<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\DemoGameSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * The demo game a developer works against, who runs it, and who plays it.
 *
 * Signing in with Discord locally would otherwise land on a player's dashboard:
 * the only Control account the seeder makes is a password login, so a Discord
 * account has nothing to be Control of until it is seated. Every character gets
 * a password login too, since claiming a seat by Discord handle would want a
 * Discord account per player before a developer could see the game from one.
 */
class DemoGameSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run the seeder the way `$this->call()` does, which is the only path that
     * can pass it an argument. It reports to a console, so it is given one
     * writing into a buffer rather than the test's output.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function seedDemoGame(array $parameters = []): Game
    {
        $console = new Command;
        $console->setLaravel($this->app);
        $console->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        app(DemoGameSeeder::class)
            ->setContainer($this->app)
            ->setCommand($console)
            ->__invoke($parameters);

        return Game::query()->latest('id')->firstOrFail();
    }

    public function test_the_configured_handles_are_seated_on_the_control_team(): void
    {
        config()->set('running_hot.demo_control_discord', '@Patrick_Rose, someone_else');

        $game = $this->seedDemoGame();

        $this->assertSame(
            ['patrick_rose', 'someone_else'],
            $game->controlMembers()->orderBy('id')->pluck('discord_username')->all(),
        );
    }

    public function test_an_argument_beats_the_configured_handles(): void
    {
        config()->set('running_hot.demo_control_discord', 'someone_else');

        $game = $this->seedDemoGame(['controlDiscord' => 'patrick_rose']);

        $this->assertSame(
            ['patrick_rose'],
            $game->controlMembers()->pluck('discord_username')->all(),
        );
    }

    public function test_a_developer_who_has_already_signed_in_is_bound_at_once(): void
    {
        $user = User::factory()->create(['discord_username' => 'Patrick_Rose']);
        config()->set('running_hot.demo_control_discord', 'patrick_rose');

        $game = $this->seedDemoGame();

        $this->assertSame($user->id, $game->controlMembers()->sole()->user_id);
        $this->assertTrue($user->fresh()->isControlFor($game));
    }

    public function test_every_character_is_claimed_by_a_login_of_its_own(): void
    {
        $game = $this->seedDemoGame();

        $characters = $game->characters()->get();

        $this->assertGreaterThan(0, $characters->count());
        $this->assertSame(
            0,
            $game->characters()->whereNull('user_id')->count(),
            'Every character in the demo game should have a login to sign in as.',
        );
        $this->assertSame(
            $characters->count(),
            $characters->pluck('user_id')->unique()->count(),
            'No two characters should share an account.',
        );
    }

    public function test_a_character_login_is_its_name_and_the_shared_password(): void
    {
        $game = $this->seedDemoGame();

        $character = $game->characters()
            ->where('name', 'Augmented Nucleotech Corp Security')
            ->sole();

        $this->assertSame(CharacterRole::Security, $character->role);

        $user = $character->user()->sole();

        $this->assertSame('augmented-nucleotech-corp-security@example.com', $user->email);
        $this->assertSame($character->name, $user->name);
        $this->assertFalse($user->isControl(), 'A player is not Control.');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_control_signs_in_with_the_same_shared_password(): void
    {
        $this->seedDemoGame();

        $this->post(route('login.store'), [
            'email' => 'control@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs(
            User::query()->where('email', 'control@example.com')->sole(),
        );
    }

    public function test_a_login_somebody_else_holds_is_numbered_rather_than_refused(): void
    {
        User::factory()->create(['email' => 'jack-scanton@example.com']);

        $game = $this->seedDemoGame();

        $character = $game->characters()->where('name', 'Jack Scanton')->sole();

        $this->assertSame('jack-scanton-2@example.com', $character->user()->sole()->email);
    }

    public function test_seeding_twice_gives_the_second_game_its_own_logins(): void
    {
        $first = $this->seedDemoGame();
        $second = $this->seedDemoGame();

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(0, $second->characters()->whereNull('user_id')->count());
        $this->assertSame(
            0,
            Character::query()
                ->whereIn('user_id', $first->characters()->pluck('user_id'))
                ->where('game_id', $second->id)
                ->count(),
            'A second game must not take the first game\'s accounts.',
        );
    }

    public function test_no_configured_handle_leaves_the_control_team_empty(): void
    {
        config()->set('running_hot.demo_control_discord', '');

        $this->seedDemoGame();

        $this->assertSame(0, ControlMember::query()->count());
        $this->assertTrue(
            User::query()->where('email', 'control@example.com')->sole()->isControlEverywhere(),
        );
    }
}
