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
        $this->assertSame('Shiv', $groups[0]['members'][0]['cards'][0]['name']);
        $this->assertSame(2, $groups[0]['members'][0]['cards'][0]['copies']);
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

    public function test_control_sees_everybody(): void
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
     * A Corporate seat has a hand of its own now, and sees it rather than
     * nothing: 2.1 has Runners buying equipment "from other players", so a CEO
     * may be holding the card they bought to hand over. What they still do not
     * see is anybody else's.
     */
    public function test_a_corporate_player_sees_their_own_hand_and_nobody_elses(): void
    {
        $user = User::factory()->create();
        $corporation = Corporation::factory()->for($this->game)->create();

        $ceo = Character::factory()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $corporation->id,
            'user_id' => $user->id,
            'name' => 'Ada Bellweather',
            'role' => CharacterRole::Ceo,
        ]);

        $this->give($ceo, 'Katana');

        $runner = $this->runner('Wicker');
        $this->give($runner, 'Shiv');

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game, $user);

        $this->assertSame(['Ada Bellweather'], $this->namesIn($groups));
        $this->assertSame('Katana', $groups[0]['members'][0]['cards'][0]['name']);
        $this->assertStringNotContainsString('Shiv', json_encode($groups) ?: '');
    }

    /**
     * The three groups, in the order the page draws them: the gangs whose game
     * this mostly is, then the Corporations, then everybody in neither.
     */
    public function test_the_whole_game_is_grouped_by_team(): void
    {
        $corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);

        Character::factory()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $corporation->id,
            'name' => 'Gordon CEO',
            'role' => CharacterRole::Ceo,
        ]);

        $this->runner('Wicker');

        Character::factory()->create([
            'game_id' => $this->game->id,
            'name' => 'HM Government',
            'role' => CharacterRole::Other,
        ]);

        $groups = app(GamePresenter::class)->equipmentHoldings($this->game);

        $this->assertSame(
            ['gang:'.$this->gang->id, 'corporation:'.$corporation->id, 'unaffiliated'],
            array_column($groups, 'key'),
        );
        $this->assertSame(['Facers', 'Gordon', 'Unaffiliated'], array_column($groups, 'name'));
        $this->assertSame(['Wicker', 'Gordon CEO', 'HM Government'], $this->namesIn($groups));
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

    /**
     * A player reading their kit before the game starts, which is most of what
     * a Draft game is for. ReadOnlyGameViewTest holds the rest of that line.
     */
    public function test_the_page_renders_before_the_game_starts(): void
    {
        Game::query()->update(['status' => GameStatus::Draft]);

        $this->actingAs(User::factory()->create())->get('/equipment')->assertOk();
    }

    public function test_the_page_renders_with_no_game_at_all(): void
    {
        Game::query()->delete();

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
            /** @var array<int, array<string, mixed>> $members */
            $members = $group['members'];

            foreach ($members as $member) {
                $names[] = (string) $member['name'];
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

    private function give(Character $character, string $cardName, int $copies = 1): void
    {
        $card = EquipmentCardType::factory()->for($this->game)->create(['name' => $cardName]);

        app(EquipmentService::class)->setCopiesInHand($character, $card, $copies);
    }
}
