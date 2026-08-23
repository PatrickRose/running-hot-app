<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Jobs\AdvancePhase;
use App\Jobs\SendDiscordAnnouncement;
use App\Models\Game;
use App\Services\TurnEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PhaseSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tick_command_advances_an_overdue_phase(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $phase = app(TurnEngine::class)->start($game);

        Carbon::setTestNow('2026-08-08 12:20:00');

        $this->artisan('game:tick')->assertSuccessful();

        $this->assertSame(PhaseStatus::Completed, $phase->fresh()->status);
        $this->assertSame(PhaseType::Action, $game->fresh()->currentPhase()?->type);

        Carbon::setTestNow();
    }

    public function test_the_tick_command_leaves_a_phase_that_still_has_time(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $phase = app(TurnEngine::class)->start($game);

        Carbon::setTestNow('2026-08-08 12:05:00');

        $this->artisan('game:tick')->assertSuccessful();

        $this->assertSame(PhaseStatus::Running, $phase->fresh()->status);
        $this->assertSame(PhaseType::Setup, $game->fresh()->currentPhase()?->type);

        Carbon::setTestNow();
    }

    public function test_the_tick_command_ignores_a_paused_phase(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $engine = app(TurnEngine::class);
        $phase = $engine->start($game);
        $engine->pause($phase);

        Carbon::setTestNow('2026-08-08 12:30:00');

        $this->artisan('game:tick')->assertSuccessful();

        $this->assertSame(PhaseStatus::Paused, $phase->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_the_tick_command_ignores_games_set_to_manual_advance(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->manuallyAdvanced()->create(['setup_seconds' => 900]);
        $phase = app(TurnEngine::class)->start($game);

        Carbon::setTestNow('2026-08-08 12:30:00');

        $this->artisan('game:tick')->assertSuccessful();

        $this->assertSame(PhaseStatus::Running, $phase->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_the_tick_command_ignores_a_finished_game(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $phase = app(TurnEngine::class)->start($game);
        $game->forceFill(['status' => GameStatus::Finished])->save();

        Carbon::setTestNow('2026-08-08 12:30:00');

        $this->artisan('game:tick')->assertSuccessful();

        $this->assertSame(1, $game->fresh()->turns()->count(), 'A finished game must not gain a turn.');
        $this->assertSame(PhaseStatus::Running, $phase->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_a_stale_advance_job_does_nothing(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $engine = app(TurnEngine::class);
        $phase = $engine->start($game);

        // Control extends the phase after the job was queued, bumping the version.
        $engine->extend($phase, 600);

        Carbon::setTestNow('2026-08-08 12:20:00');

        (new AdvancePhase($phase->id, 0))->handle($engine);

        $this->assertSame(PhaseStatus::Running, $phase->fresh()->status, 'A superseded job must stand down.');

        Carbon::setTestNow();
    }

    public function test_an_advance_job_that_fires_early_does_not_cut_the_phase_short(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $engine = app(TurnEngine::class);
        $phase = $engine->start($game);

        (new AdvancePhase($phase->id, $phase->version))->handle($engine);

        $this->assertSame(PhaseStatus::Running, $phase->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_a_current_advance_job_rolls_the_phase_over(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');

        $game = Game::factory()->create(['setup_seconds' => 900]);
        $engine = app(TurnEngine::class);
        $phase = $engine->start($game);

        Carbon::setTestNow('2026-08-08 12:20:00');

        (new AdvancePhase($phase->id, $phase->version))->handle($engine);

        $this->assertSame(PhaseStatus::Completed, $phase->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_phase_changes_are_announced_to_discord(): void
    {
        Queue::fake();

        $game = Game::factory()->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/1/abc',
        ]);

        app(TurnEngine::class)->start($game);

        Queue::assertPushed(SendDiscordAnnouncement::class, fn (SendDiscordAnnouncement $job): bool => str_contains($job->content, 'Setup phase has begun'));
    }

    public function test_the_announcement_is_posted_to_the_games_own_webhook(): void
    {
        Http::fake();

        $game = Game::factory()->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/1/abc',
        ]);

        app(TurnEngine::class)->start($game);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://discord.com/api/webhooks/1/abc'
            && str_contains((string) $request['content'], 'Setup phase has begun'));
    }

    public function test_each_game_announces_to_its_own_channel(): void
    {
        Http::fake();

        $first = Game::factory()->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/111/aaa',
        ]);
        $second = Game::factory()->create([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/222/bbb',
        ]);

        app(TurnEngine::class)->start($first);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://discord.com/api/webhooks/111/aaa');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === $second->discord_webhook_url);
    }
}
