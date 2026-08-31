<?php

namespace Tests\Feature;

use App\Models\ControlMember;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\DemoGameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo game a developer works against, and who runs it.
 *
 * Signing in with Discord locally would otherwise land on a player's dashboard:
 * the only Control account the seeder makes is a password login, so a Discord
 * account has nothing to be Control of until it is seated.
 */
class DemoGameSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function seedDemoGame(array $parameters = []): Game
    {
        app(DemoGameSeeder::class)->setContainer($this->app)->__invoke($parameters);

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
