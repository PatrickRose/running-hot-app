<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The rulebook and the game's background reading, which every player is
 * offered from the sidebar whatever seat they hold.
 *
 * The rulebook is the PDF in docs/, the same for every game. The background is
 * a link Control sets per game, and there is nothing to offer until it has.
 */
class ReferenceReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_player_is_served_the_rulebook_pdf(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('rulebook'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame(
            base_path('docs/running-hot-rulebook.pdf'),
            $response->baseResponse->getFile()->getPathname(),
        );
    }

    public function test_a_visitor_is_sent_to_sign_in_for_the_rulebook(): void
    {
        $this->get(route('rulebook'))->assertRedirect(route('login'));
    }

    public function test_control_can_set_a_games_background_link(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->control()->create())
            ->post(route('control.games.background', $game), [
                'background_url' => 'https://docs.example.com/procatorion-briefing',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('https://docs.example.com/procatorion-briefing', $game->fresh()->background_url);
    }

    public function test_a_background_link_can_be_cleared(): void
    {
        $game = Game::factory()->create(['background_url' => 'https://docs.example.com/old']);

        $this->actingAs(User::factory()->control()->create())
            ->post(route('control.games.background', $game), ['background_url' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($game->fresh()->background_url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedLinks(): array
    {
        return [
            'not a url' => ['procatorion briefing'],
            'a script' => ['javascript:alert(1)'],
            'not the web' => ['ftp://example.com/briefing.pdf'],
        ];
    }

    /**
     * Every player's sidebar links straight to it, so it has to be somewhere
     * a browser can open - and never a script.
     */
    #[DataProvider('rejectedLinks')]
    public function test_a_background_link_must_be_a_web_address(string $url): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->control()->create())
            ->post(route('control.games.background', $game), ['background_url' => $url])
            ->assertSessionHasErrors('background_url');

        $this->assertNull($game->fresh()->background_url);
    }

    public function test_a_player_cannot_set_the_background_link(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('control.games.background', $game), [
                'background_url' => 'https://example.com/not-the-briefing',
            ])
            ->assertForbidden();

        $this->assertNull($game->fresh()->background_url);
    }

    public function test_every_page_shares_the_current_games_background_link(): void
    {
        Game::factory()->create([
            'status' => GameStatus::Running,
            'background_url' => 'https://docs.example.com/procatorion-briefing',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reference.background_url', 'https://docs.example.com/procatorion-briefing'));
    }

    public function test_no_background_link_is_shared_until_control_sets_one(): void
    {
        Game::factory()->create(['status' => GameStatus::Running]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reference.background_url', null));
    }

    public function test_the_control_panel_shows_the_link_it_is_editing(): void
    {
        $game = Game::factory()->create(['background_url' => 'https://docs.example.com/procatorion-briefing']);

        $this->actingAs(User::factory()->control()->create())
            ->get(route('control.games.show', $game))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('game.background_url', 'https://docs.example.com/procatorion-briefing'));
    }
}
