<?php

namespace Tests\Feature;

use App\Actions\SeedEquipmentHoldings;
use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\Game;
use App\Models\Gang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Equipment each Runner opens the game carrying (rulebook 3.4.1).
 *
 * Given per player: the briefings are one document per Runner, so a kit is
 * configured beside that Runner's own stats and nobody else's.
 */
class EquipmentSeedingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each Runner's kit is their own, and a gangmate's is not theirs.
     */
    public function test_a_runner_gets_the_kit_configured_against_their_name(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->create(['game_id' => $game->id, 'name' => 'Facers']);

        $con = $this->runner($game, $gang, 'Con');
        $ghost = $this->runner($game, $gang, 'Ghost');

        $this->configure([[
            'name' => 'Facers',
            'runners' => [
                ['name' => 'Con', 'equipment' => ['EEP002' => 1]],
                ['name' => 'Ghost', 'equipment' => ['EEP003' => 2]],
            ],
        ]]);

        $seeded = app(SeedEquipmentHoldings::class)->handle($game);

        $this->assertSame(2, $seeded['runners']);
        $this->assertSame(1, $con->equipmentCopiesOf($this->codeId($game, 'EEP002')));
        $this->assertSame(0, $con->equipmentCopiesOf($this->codeId($game, 'EEP003')));
        $this->assertSame(2, $ghost->equipmentCopiesOf($this->codeId($game, 'EEP003')));
        $this->assertSame(0, $ghost->equipmentCopiesOf($this->codeId($game, 'EEP002')));
    }

    /**
     * A Runner in a configured gang who has no list of their own starts with
     * nothing: there is no gang-level kit to fall back on, because the
     * briefings do not work that way.
     */
    public function test_a_runner_with_no_list_of_their_own_starts_with_nothing(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->create(['game_id' => $game->id, 'name' => 'Facers']);

        $this->runner($game, $gang, 'Next');

        $this->configure([[
            'name' => 'Facers',
            'runners' => [['name' => 'Con', 'equipment' => ['EEP002' => 1]]],
        ]]);

        $seeded = app(SeedEquipmentHoldings::class)->handle($game);

        $this->assertSame(0, $seeded['runners']);
        $this->assertDatabaseCount('equipment_holdings', 0);
    }

    /**
     * A gang the configuration says nothing about opens with nothing, rather
     * than a guessed kit - the same choice the Facilities make.
     */
    public function test_an_unconfigured_gang_starts_with_nothing(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->create(['game_id' => $game->id, 'name' => 'Some Gang Control Invented']);

        $this->runner($game, $gang, 'Nobody');

        $this->configure([]);

        $seeded = app(SeedEquipmentHoldings::class)->handle($game);

        $this->assertSame(0, $seeded['runners']);
        $this->assertDatabaseCount('equipment_holdings', 0);
    }

    /**
     * A Freelancer belongs to no gang and is named directly.
     */
    public function test_a_freelancer_is_named_directly(): void
    {
        $game = Game::factory()->create();

        $freelancer = Character::factory()->create([
            'game_id' => $game->id,
            'gang_id' => null,
            'name' => 'Jack Scanton',
            'role' => CharacterRole::Freelancer,
        ]);

        config(['running_hot.gangs' => [], 'running_hot.freelancer_equipment' => [
            'Jack Scanton' => ['EEP002' => 1],
        ]]);

        app(SeedEquipmentHoldings::class)->handle($game);

        $this->assertSame(1, $freelancer->equipmentCopiesOf($this->codeId($game, 'EEP002')));
    }

    /**
     * The catalogue is Control's. A briefing naming a card they have deleted is
     * not a reason to put it back.
     */
    public function test_a_code_the_catalogue_does_not_have_is_skipped(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->create(['game_id' => $game->id, 'name' => 'Facers']);

        $this->runner($game, $gang, 'Con');

        $this->configure([[
            'name' => 'Facers',
            'runners' => [['name' => 'Con', 'equipment' => ['NOPE999' => 3]]],
        ]]);

        $seeded = app(SeedEquipmentHoldings::class)->handle($game);

        $this->assertSame(0, $seeded['copies']);
        $this->assertDatabaseCount('equipment_holdings', 0);
    }

    /**
     * Re-running only ever adds a card a Runner has no row for at all, so it
     * cannot refill a hand spent during play.
     */
    public function test_reseeding_does_not_refill_a_spent_hand(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->create(['game_id' => $game->id, 'name' => 'Facers']);

        $con = $this->runner($game, $gang, 'Con');

        $this->configure([[
            'name' => 'Facers',
            'runners' => [['name' => 'Con', 'equipment' => ['EEP002' => 2]]],
        ]]);

        $action = app(SeedEquipmentHoldings::class);
        $action->handle($game);

        $typeId = $this->codeId($game, 'EEP002');
        $con->equipmentHoldings()->where('equipment_card_type_id', $typeId)
            ->update(['copies' => 0]);

        $action->handle($game);

        $this->assertSame(0, $con->equipmentCopiesOf($typeId));
    }

    /**
     * @param  array<int, array<string, mixed>>  $gangs
     */
    private function configure(array $gangs): void
    {
        config(['running_hot.gangs' => $gangs, 'running_hot.freelancer_equipment' => []]);
    }

    private function runner(Game $game, Gang $gang, string $name): Character
    {
        return Character::factory()->create([
            'game_id' => $game->id,
            'gang_id' => $gang->id,
            'name' => $name,
            'role' => CharacterRole::Runner,
        ]);
    }

    private function codeId(Game $game, string $code): int
    {
        return (int) $game->equipmentCardTypes()->where('code', $code)->value('id');
    }
}
