<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\User;
use App\Services\TurnEngine;
use App\Support\GamePresenter;
use App\Support\LogoImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ControlPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    public function test_a_player_cannot_reach_the_control_panel(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/control/games/{$game->id}")
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $game = Game::factory()->create();

        $this->get("/control/games/{$game->id}")->assertRedirect('/login');
    }

    public function test_control_can_open_the_dashboard(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->get("/control/games/{$game->id}")
            ->assertOk();
    }

    public function test_control_can_start_and_advance_the_clock(): void
    {
        $game = Game::factory()->create();
        $user = $this->control();

        $this->actingAs($user)
            ->post("/control/games/{$game->id}/phase/start")
            ->assertRedirect();

        $phase = $game->fresh()->currentPhase();
        $this->assertNotNull($phase);
        $this->assertSame(PhaseType::Setup, $phase->type);

        $this->actingAs($user)
            ->post("/control/games/{$game->id}/phase/advance")
            ->assertRedirect();

        $this->assertSame(PhaseType::Action, $game->fresh()->currentPhase()?->type);
    }

    public function test_control_can_pause_and_resume(): void
    {
        $game = Game::factory()->create();
        $user = $this->control();
        app(TurnEngine::class)->start($game);

        $this->actingAs($user)->post("/control/games/{$game->id}/phase/pause");
        $this->assertSame(PhaseStatus::Paused, $game->fresh()->currentPhase()?->status);

        $this->actingAs($user)->post("/control/games/{$game->id}/phase/resume");
        $this->assertSame(PhaseStatus::Running, $game->fresh()->currentPhase()?->status);
    }

    public function test_extending_requires_a_non_zero_amount(): void
    {
        $game = Game::factory()->create();
        app(TurnEngine::class)->start($game);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/phase/extend", ['seconds' => 0])
            ->assertSessionHasErrors('seconds');
    }

    public function test_advancing_a_game_that_has_not_started_is_a_validation_error(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/phase/advance")
            ->assertSessionHasErrors('phase');
    }

    /**
     * A character that is an organisation carries its own logo; a character
     * that is a person carries none, and the page draws nothing rather than
     * falling back to the initials a faction gets. Forty-odd coloured squares
     * would imply an organisation where there is only somebody's name.
     */
    public function test_only_a_character_that_is_an_organisation_carries_a_logo(): void
    {
        $game = Game::factory()->create();
        $outlet = Character::factory()->for($game)->create(['name' => 'Test Evening Herald']);
        $person = Character::factory()->for($game)->create(['name' => 'Test Someone Ordinary']);

        $directory = public_path(LogoImage::DIRECTORY);
        File::ensureDirectoryExists($directory);
        $path = $directory.'/test-evening-herald.png';

        $this->assertFileDoesNotExist(
            $path,
            'A test must never write over the game\'s own artwork.',
        );

        File::put($path, 'not really an image');
        LogoImage::flush();

        try {
            $characters = collect(app(GamePresenter::class)->trackers($game)['characters']);

            $this->assertSame(
                '/images/logos/test-evening-herald.png',
                $characters->firstWhere('subject_id', $outlet->id)['logo_path'],
            );
            $this->assertNull($characters->firstWhere('subject_id', $person->id)['logo_path']);
        } finally {
            File::delete($path);
            LogoImage::flush();
        }
    }

    public function test_control_can_adjust_a_tracker(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['political_will' => 5]);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/trackers", [
                'subject_type' => 'corporation',
                'subject_id' => $corporation->id,
                'tracker' => 'political_will',
                'mode' => 'adjust',
                'value' => -2,
                'reason' => 'Reneged on a deal',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $corporation->fresh()->political_will);
    }

    public function test_a_tracker_cannot_be_applied_to_a_subject_it_does_not_belong_to(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/trackers", [
                'subject_type' => 'corporation',
                'subject_id' => $corporation->id,
                'tracker' => 'notoriety',
                'mode' => 'adjust',
                'value' => 1,
            ])
            ->assertSessionHasErrors('tracker');
    }

    public function test_a_tracker_cannot_target_a_subject_from_another_game(): void
    {
        $game = Game::factory()->create();
        $other = Corporation::factory()->for(Game::factory()->create())->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/trackers", [
                'subject_type' => 'corporation',
                'subject_id' => $other->id,
                'tracker' => 'income',
                'mode' => 'set',
                'value' => 999,
            ])
            ->assertSessionHasErrors('subject_id');

        $this->assertNotSame(999, $other->fresh()->income);
    }

    public function test_a_player_cannot_adjust_trackers(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['income' => 1]);

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/trackers", [
                'subject_type' => 'corporation',
                'subject_id' => $corporation->id,
                'tracker' => 'income',
                'mode' => 'set',
                'value' => 500,
            ])
            ->assertForbidden();

        $this->assertSame(1, $corporation->fresh()->income);
    }

    public function test_a_tag_costs_three_credits_during_team_time(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create([
            'tags' => 2,
            'credits' => 10,
        ]);

        $engine = app(TurnEngine::class);
        $engine->advance($engine->advance($engine->start($game)));

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/characters/{$character->id}/remove-tag")
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $character->fresh()->tags);
        $this->assertSame(7, $character->fresh()->credits);
    }

    public function test_a_tag_cannot_be_bought_off_outside_team_time(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create(['tags' => 1, 'credits' => 10]);

        app(TurnEngine::class)->start($game);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/characters/{$character->id}/remove-tag")
            ->assertSessionHasErrors('tags');

        $this->assertSame(1, $character->fresh()->tags);
    }

    public function test_a_tag_cannot_be_bought_off_without_the_credits(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create(['tags' => 1, 'credits' => 2]);

        $engine = app(TurnEngine::class);
        $engine->advance($engine->advance($engine->start($game)));

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/characters/{$character->id}/remove-tag")
            ->assertSessionHasErrors('tags');

        $this->assertSame(1, $character->fresh()->tags);
        $this->assertSame(2, $character->fresh()->credits);
    }

    public function test_control_can_create_a_game(): void
    {
        $this->actingAs($this->control())
            ->post('/control/games', [
                'name' => 'Running Hot — Sheffield',
                'setup_seconds' => 600,
                'discord_webhook_url' => 'https://discord.com/api/webhooks/123456789/abcdef-ghij',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('games', [
            'name' => 'Running Hot — Sheffield',
            'setup_seconds' => 600,
        ]);
    }

    /**
     * Provisioning a game's Discord server creates the webhook, so requiring
     * one at creation asked Control for the thing about to be made for them.
     */
    public function test_a_game_can_be_created_without_a_webhook(): void
    {
        $this->actingAs($this->control())
            ->post('/control/games', ['name' => 'No Channel Yet'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('games', [
            'name' => 'No Channel Yet',
            'discord_webhook_url' => null,
        ]);
    }

    public function test_creating_a_game_can_go_straight_on_to_its_discord_server(): void
    {
        config()->set('services.discord.client_id', 'app-123');

        // Setting the server up is the rest of creating a game, so creation
        // hands over to the bot-add flow rather than stopping at a made game.
        $this->actingAs($this->control())
            ->post('/control/games', ['name' => 'Straight To Discord', 'connect_discord' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirectContains('/discord/connect');

        $game = Game::query()->where('name', 'Straight To Discord')->sole();

        $this->assertNull($game->discord_webhook_url, 'Provisioning is what creates the webhook.');
    }

    public function test_creating_a_game_without_the_flag_lands_on_the_game(): void
    {
        $response = $this->actingAs($this->control())
            ->post('/control/games', ['name' => 'No Discord Yet']);

        $game = Game::query()->where('name', 'No Discord Yet')->sole();

        $response->assertRedirect("/control/games/{$game->id}");
    }

    public function test_a_game_without_a_webhook_announces_nowhere_instead_of_failing(): void
    {
        $game = Game::factory()->withoutDiscordWebhook()->create();

        // The clock must run whether or not Discord is set up yet, and there is
        // no global webhook to fall back to.
        app(TurnEngine::class)->start($game);

        $this->assertNotNull($game->fresh()->currentPhase());
        Http::assertNothingSent();
    }

    /**
     * A channel link or invite pasted by mistake would otherwise only surface as
     * a failed announcement mid-game.
     */
    #[DataProvider('rejectedWebhooks')]
    public function test_a_url_that_is_not_a_discord_webhook_is_rejected(string $url): void
    {
        $this->actingAs($this->control())
            ->post('/control/games', [
                'name' => 'Bad Webhook',
                'discord_webhook_url' => $url,
            ])
            ->assertSessionHasErrors('discord_webhook_url');

        $this->assertDatabaseMissing('games', ['name' => 'Bad Webhook']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedWebhooks(): array
    {
        return [
            'a channel link' => ['https://discord.com/channels/123/456'],
            'an invite' => ['https://discord.gg/abcdef'],
            'another host' => ['https://example.com/api/webhooks/123/abc'],
            'not a url' => ['webhooks/123/abc'],
        ];
    }

    public function test_control_can_repoint_a_game_at_a_different_channel(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/webhook", [
                'discord_webhook_url' => 'https://discord.com/api/webhooks/999888777/new-token',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'https://discord.com/api/webhooks/999888777/new-token',
            $game->fresh()->discord_webhook_url,
        );
    }

    /**
     * A dead webhook that looks configured is worse than none: every
     * announcement fails and nothing on the panel says why.
     */
    public function test_a_webhook_can_be_cleared(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/webhook", ['discord_webhook_url' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($game->fresh()->discord_webhook_url);
    }

    public function test_a_player_cannot_repoint_the_webhook(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/webhook", [
                'discord_webhook_url' => 'https://discord.com/api/webhooks/1/hijack',
            ])
            ->assertForbidden();
    }
}
