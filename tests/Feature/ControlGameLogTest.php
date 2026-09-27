<?php

namespace Tests\Feature;

use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Phase;
use App\Models\Turn;
use App\Models\User;
use App\Services\TrackerService;
use App\Support\GameLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Control reading back the whole tracker ledger, not just the last forty rows
 * the Stats page carries.
 */
class ControlGameLogTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private TrackerService $trackers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->running()->create();
        $this->trackers = app(TrackerService::class);
    }

    public function test_a_player_cannot_read_the_log(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('control.log.index', $this->game))
            ->assertForbidden();
    }

    public function test_control_of_another_game_cannot_read_this_ones_log(): void
    {
        $user = User::factory()->create();
        ControlMember::factory()->for(Game::factory()->running())->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('control.log.index', $this->game))
            ->assertForbidden();
    }

    public function test_every_movement_is_paged_newest_first_with_the_turn_it_happened_in(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);
        $this->startPhase(3, PhaseType::Action);

        foreach (range(1, GameLogPresenter::PER_PAGE + 5) as $movement) {
            $this->trackers->adjust($corporation, Tracker::CorporationCredits, 1, "Movement {$movement}");
        }

        $this->actingAs($this->control())
            ->get(route('control.log.index', $this->game))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('control/games/log')
                ->where('adjustments.total', GameLogPresenter::PER_PAGE + 5)
                ->where('adjustments.last_page', 2)
                ->has('adjustments.data', GameLogPresenter::PER_PAGE)
                ->where('adjustments.data.0.reason', 'Movement '.(GameLogPresenter::PER_PAGE + 5))
                ->where('adjustments.data.0.subject', 'Gordon')
                ->where('adjustments.data.0.turn', 3)
                ->where('adjustments.data.0.phase', 'Action')
                ->where('options.turns', [3])
            );

        $this->actingAs($this->control())
            ->get(route('control.log.index', ['game' => $this->game, 'page' => 2]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('adjustments.data', 5)
                ->where('adjustments.data.4.reason', 'Movement 1')
            );
    }

    public function test_the_log_narrows_by_subject_tracker_and_turn(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);
        $character = Character::factory()->for($this->game)->create(['name' => 'Jack Scanton']);

        $this->startPhase(1, PhaseType::Setup);
        $this->trackers->adjust($corporation, Tracker::CorporationCredits, 5, 'Turn one credits');
        $this->trackers->adjust($character, Tracker::Wounds, 1, 'Turn one wound');

        $this->startPhase(2, PhaseType::Action);
        $this->trackers->adjust($corporation, Tracker::Income, 2, 'Turn two income');
        $this->trackers->adjust($character, Tracker::Wounds, 1, 'Turn two wound');

        $control = $this->control();

        $this->actingAs($control)
            ->get(route('control.log.index', ['game' => $this->game, 'subject' => 'character:'.$character->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('adjustments.total', 2)
                ->where('adjustments.data.0.reason', 'Turn two wound')
                ->where('filters.subject', 'character:'.$character->id)
            );

        $this->actingAs($control)
            ->get(route('control.log.index', ['game' => $this->game, 'tracker' => Tracker::Income->value]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('adjustments.total', 1)
                ->where('adjustments.data.0.reason', 'Turn two income')
            );

        $this->actingAs($control)
            ->get(route('control.log.index', ['game' => $this->game, 'turn' => 1, 'subject' => 'character:'.$character->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('adjustments.total', 1)
                ->where('adjustments.data.0.reason', 'Turn one wound')
                ->where('options.turns', [2, 1])
                ->where('options.subjects', [
                    ['value' => 'corporation:'.$corporation->id, 'label' => 'Gordon'],
                    ['value' => 'character:'.$character->id, 'label' => 'Jack Scanton'],
                ])
            );
    }

    public function test_another_games_movements_never_appear(): void
    {
        $elsewhere = Corporation::factory()->for(Game::factory()->running())->create();
        $this->trackers->adjust($elsewhere, Tracker::CorporationCredits, 5, 'Somebody else');

        $this->actingAs($this->control())
            ->get(route('control.log.index', $this->game))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('adjustments.total', 0)
                ->where('options.subjects', [])
            );
    }

    /**
     * Ends whatever phase is running and opens a new one, so the next movement
     * is recorded against it.
     */
    private function startPhase(int $turnNumber, PhaseType $type): void
    {
        Phase::query()
            ->whereHas('turn', fn ($turns) => $turns->whereBelongsTo($this->game))
            ->update(['status' => PhaseStatus::Completed]);

        $turn = Turn::query()->firstOrCreate(['game_id' => $this->game->id, 'number' => $turnNumber]);

        Phase::factory()->for($turn)->ofType($type)->create();
    }

    private function control(): User
    {
        $user = User::factory()->create();
        ControlMember::factory()->for($this->game)->create(['user_id' => $user->id]);

        return $user;
    }
}
