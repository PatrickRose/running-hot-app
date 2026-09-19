<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use App\Services\EquipmentService;
use App\Support\GamePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a player may see of the Equipment (rulebook 3.4.1).
 *
 * One tier line and it is the whole of the page: you see the hands of the
 * characters you have claimed, and Control sees everybody. The rulebook does
 * not make a hand Secret the way 3.4.2 makes a Facility's stack, so this is a
 * ruling rather than a reading — which is exactly why it is worth a test, since
 * nothing else would catch a payload quietly widening.
 */
class PlayerEquipmentPageTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Gang $gang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->gang = Gang::factory()->for($this->game)->create(['name' => 'Facers']);
    }

    public function test_a_runner_sees_their_own_hand(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner('Wicker', $user);

        $this->give($wicker, 'Shiv', 2);

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game, $user);

        $this->assertSame(['Wicker'], $this->namesIn($groups));
        $this->assertSame('Shiv', $groups[0]['runners'][0]['cards'][0]['name']);
        $this->assertSame(2, $groups[0]['runners'][0]['cards'][0]['copies']);
    }

    /**
     * The line that matters. A gangmate's hand is theirs, and at the table you
     * would have to ask them what they are carrying.
     */
    public function test_a_runner_does_not_see_a_gangmates_hand(): void
    {
        $user = User::factory()->create();
        $this->runner('Wicker', $user);

        $ghost = $this->runner('Ghost');
        $this->give($ghost, 'Katana');

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game, $user);

        $this->assertSame(['Wicker'], $this->namesIn($groups));
        $this->assertStringNotContainsString('Katana', json_encode($groups) ?: '');
    }

    /**
     * Somebody holding two seats reads both, because both are theirs.
     */
    public function test_a_player_holding_two_runners_sees_both(): void
    {
        $user = User::factory()->create();
        $this->runner('Wicker', $user);
        $this->runner('Con', $user);
        $this->runner('Ghost');

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game, $user);

        $this->assertSame(['Con', 'Wicker'], $this->namesIn($groups));
    }

    public function test_control_sees_every_runner(): void
    {
        $control = User::factory()->control()->create();

        $this->runner('Wicker');
        $this->runner('Ghost');
        Character::factory()->create([
            'game_id' => $this->game->id,
            'name' => 'Jack Scanton',
            'role' => CharacterRole::Freelancer,
        ]);

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game, $control);

        $this->assertSame(['Ghost', 'Wicker', 'Jack Scanton'], $this->namesIn($groups));
    }

    /**
     * Passing nobody is the Control panel's own call, which is the whole game.
     */
    public function test_no_viewer_is_the_whole_game(): void
    {
        $this->runner('Wicker');
        $this->runner('Ghost');

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game);

        $this->assertSame(['Ghost', 'Wicker'], $this->namesIn($groups));
    }

    /**
     * Equipment is carried by the side that runs, so a Corporate seat has no
     * hand to read — and is told that rather than shown somebody else's.
     */
    public function test_a_corporate_player_sees_nothing(): void
    {
        $user = User::factory()->create();
        $corporation = Corporation::factory()->for($this->game)->create();

        Character::factory()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $corporation->id,
            'user_id' => $user->id,
            'name' => 'Ada Bellweather',
            'role' => CharacterRole::Ceo,
        ]);

        $runner = $this->runner('Wicker');
        $this->give($runner, 'Shiv');

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game, $user);

        $this->assertSame([], $groups);
    }

    public function test_a_player_who_has_claimed_nobody_sees_nothing(): void
    {
        $this->runner('Wicker');

        $groups = app(GamePresenter::class)
            ->equipmentHoldings($this->game, User::factory()->create());

        $this->assertSame([], $groups);
    }

    public function test_the_page_renders_for_a_runner(): void
    {
        $user = User::factory()->create();
        $wicker = $this->runner('Wicker', $user);
        $this->give($wicker, 'Shiv');

        $this->actingAs($user)->get('/equipment')->assertOk();
    }

    public function test_the_page_renders_with_no_game_running(): void
    {
        Game::query()->update(['status' => GameStatus::Draft]);

        $this->actingAs(User::factory()->create())->get('/equipment')->assertOk();
    }

    public function test_the_page_needs_a_login(): void
    {
        $this->get('/equipment')->assertRedirect('/login');
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, string>
     */
    private function namesIn(array $groups): array
    {
        $names = [];

        foreach ($groups as $group) {
            /** @var array<int, array<string, mixed>> $runners */
            $runners = $group['runners'];

            foreach ($runners as $runner) {
                $names[] = (string) $runner['name'];
            }
        }

        return $names;
    }

    private function runner(string $name, ?User $user = null): Character
    {
        return Character::factory()->create([
            'game_id' => $this->game->id,
            'gang_id' => $this->gang->id,
            'user_id' => $user?->id,
            'name' => $name,
            'role' => CharacterRole::Runner,
        ]);
    }

    private function give(Character $runner, string $cardName, int $copies = 1): void
    {
        $card = EquipmentCardType::factory()->for($this->game)->create(['name' => $cardName]);

        app(EquipmentService::class)->setCopiesInHand($runner, $card, $copies);
    }
}
