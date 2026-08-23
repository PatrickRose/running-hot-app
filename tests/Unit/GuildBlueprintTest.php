<?php

namespace Tests\Unit;

use App\Enums\CharacterRole;
use App\Enums\DiscordResourceKind;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Support\Discord\GuildBlueprint;
use App\Support\Discord\PlannedChannel;
use App\Support\Discord\PlannedOverwrite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The blueprint is pure, so the shape of a Running Hot server is asserted here
 * rather than through the Discord API.
 */
class GuildBlueprintTest extends TestCase
{
    use RefreshDatabase;

    private function channel(GuildBlueprint $blueprint, string $key): PlannedChannel
    {
        foreach ($blueprint->channels() as $channel) {
            if ($channel->key === $key) {
                return $channel;
            }
        }

        $this->fail("No planned channel with key [{$key}].");
    }

    public function test_a_bare_game_still_gets_control_and_the_common_channels(): void
    {
        $blueprint = new GuildBlueprint(Game::factory()->create());

        $this->assertArrayHasKey(GuildBlueprint::ROLE_CONTROL, $blueprint->roles());

        $keys = array_map(fn (PlannedChannel $channel): string => $channel->key, $blueprint->channels());

        $this->assertContains(GuildBlueprint::CHANNEL_ANNOUNCEMENTS, $keys);
        $this->assertContains('channel:control:text', $keys);
    }

    public function test_every_corporation_and_gang_gets_a_role(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Aldermarch Dynamics']);
        $gang = Gang::factory()->for($game)->create(['name' => 'The Kestrels']);

        $roles = (new GuildBlueprint($game))->roles();

        $this->assertSame('Aldermarch Dynamics', $roles['role:corporation:'.$corporation->id]->name);
        $this->assertSame('The Kestrels', $roles['role:gang:'.$gang->id]->name);
    }

    public function test_the_corporate_functions_get_one_role_each_across_all_corporations(): void
    {
        $game = Game::factory()->create();
        Corporation::factory()->for($game)->count(2)->create();

        $roles = (new GuildBlueprint($game))->roles();

        foreach ([CharacterRole::Ceo, CharacterRole::Security, CharacterRole::Research] as $role) {
            $this->assertArrayHasKey(GuildBlueprint::functionRoleKey($role), $roles);
        }

        // Two corporations, but still one CEO role: these are deliberately
        // guild-wide so a channel can hold every CEO at once.
        $ceoRoles = array_filter(
            $roles,
            fn ($planned): bool => $planned->name === CharacterRole::Ceo->label(),
        );

        $this->assertCount(1, $ceoRoles);
    }

    public function test_a_teams_channels_are_hidden_from_everyone_else(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->for($game)->create(['name' => 'Nightshift']);

        $channel = $this->channel(new GuildBlueprint($game), 'channel:gang:'.$gang->id.':text');

        $this->assertSame('nightshift', $channel->name);
        $this->assertSame(DiscordResourceKind::TextChannel, $channel->kind);

        $targets = [];

        foreach ($channel->overwrites as $overwrite) {
            $targets[$overwrite->target] = $overwrite;
        }

        $this->assertSame(
            DiscordApi::VIEW_CHANNEL,
            $targets[PlannedOverwrite::EVERYONE]->deny & DiscordApi::VIEW_CHANNEL,
            '@everyone must be denied sight of a team channel.',
        );
        $this->assertNotSame(0, $targets[GuildBlueprint::ROLE_CONTROL]->allow & DiscordApi::VIEW_CHANNEL);
        $this->assertNotSame(0, $targets['role:gang:'.$gang->id]->allow & DiscordApi::VIEW_CHANNEL);
    }

    public function test_announcements_is_readable_by_all_and_writable_only_by_control(): void
    {
        $channel = $this->channel(
            new GuildBlueprint(Game::factory()->create()),
            GuildBlueprint::CHANNEL_ANNOUNCEMENTS,
        );

        $targets = [];

        foreach ($channel->overwrites as $overwrite) {
            $targets[$overwrite->target] = $overwrite;
        }

        $everyone = $targets[PlannedOverwrite::EVERYONE];

        $this->assertNotSame(0, $everyone->allow & DiscordApi::VIEW_CHANNEL);
        $this->assertNotSame(0, $everyone->deny & DiscordApi::SEND_MESSAGES);
        $this->assertNotSame(0, $targets[GuildBlueprint::ROLE_CONTROL]->allow & DiscordApi::SEND_MESSAGES);
    }

    public function test_a_player_gets_their_team_and_function_roles(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create();
        $user = User::factory()->create();

        Character::factory()->for($game)->for($corporation)->create([
            'user_id' => $user->id,
            'role' => CharacterRole::Security,
        ]);

        $keys = (new GuildBlueprint($game))->roleKeysForUser($user);

        $this->assertEqualsCanonicalizing([
            'role:corporation:'.$corporation->id,
            GuildBlueprint::functionRoleKey(CharacterRole::Security),
        ], $keys);
    }

    public function test_a_runner_gets_their_gang_but_no_function_role(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->for($game)->create();
        $user = User::factory()->create();

        Character::factory()->for($game)->for($gang)->create([
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
        ]);

        $this->assertSame(['role:gang:'.$gang->id], (new GuildBlueprint($game))->roleKeysForUser($user));
    }

    public function test_control_gets_the_control_role_even_without_a_character(): void
    {
        $game = Game::factory()->create();

        $keys = (new GuildBlueprint($game))->roleKeysForUser(User::factory()->control()->create());

        $this->assertSame([GuildBlueprint::ROLE_CONTROL], $keys);
    }

    public function test_a_player_in_two_teams_gets_both_roles_once_each(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->for($game)->create();
        $user = User::factory()->create();

        Character::factory()->for($game)->for($gang)->count(2)->create([
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
        ]);

        $this->assertSame(['role:gang:'.$gang->id], (new GuildBlueprint($game))->roleKeysForUser($user));
    }
}
