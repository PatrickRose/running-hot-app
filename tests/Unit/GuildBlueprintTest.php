<?php

namespace Tests\Unit;

use App\Enums\CharacterRole;
use App\Enums\DiscordResourceKind;
use App\Models\Character;
use App\Models\ControlMember;
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

    /**
     * @return array<string, PlannedOverwrite>
     */
    private function overwrites(PlannedChannel $channel): array
    {
        $targets = [];

        foreach ($channel->overwrites as $overwrite) {
            $targets[$overwrite->target] = $overwrite;
        }

        return $targets;
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

    public function test_a_seat_on_this_games_control_team_gets_the_control_role(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();
        ControlMember::factory()->for($game)->create(['user_id' => $user->id]);

        $keys = (new GuildBlueprint($game))->roleKeysForUser($user);

        $this->assertSame([GuildBlueprint::ROLE_CONTROL], $keys);
    }

    public function test_a_seat_on_another_game_gets_nothing_here(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();
        ControlMember::factory()->for(Game::factory()->create())->create(['user_id' => $user->id]);

        $this->assertSame([], (new GuildBlueprint($game))->roleKeysForUser($user));
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

    public function test_every_faction_gets_a_public_text_channel_and_three_public_voice_rooms(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Aldermarch Dynamics']);
        $gang = Gang::factory()->for($game)->create(['name' => 'Nightshift']);

        $blueprint = new GuildBlueprint($game);

        foreach (['corporation:'.$corporation->id => 'Aldermarch Dynamics', 'gang:'.$gang->id => 'Nightshift'] as $slug => $name) {
            $text = $this->channel($blueprint, GuildBlueprint::publicTeamTextKey($slug));

            $this->assertSame(DiscordResourceKind::TextChannel, $text->kind);
            $this->assertSame('category:'.$slug, $text->parentKey);

            $everyone = $this->overwrites($text)[PlannedOverwrite::EVERYONE];

            $this->assertNotSame(0, $everyone->allow & DiscordApi::VIEW_CHANNEL);
            $this->assertNotSame(0, $everyone->allow & DiscordApi::SEND_MESSAGES);
            $this->assertSame(0, $everyone->deny, 'A public channel denies nobody anything.');

            for ($room = 1; $room <= GuildBlueprint::PUBLIC_VOICE_ROOMS; $room++) {
                $voice = $this->channel($blueprint, GuildBlueprint::publicTeamVoiceKey($slug, $room));

                $this->assertSame(DiscordResourceKind::VoiceChannel, $voice->kind);
                $this->assertSame($name.' '.$room, $voice->name);

                $joiner = $this->overwrites($voice)[PlannedOverwrite::EVERYONE];

                $this->assertNotSame(0, $joiner->allow & DiscordApi::CONNECT);
                $this->assertNotSame(0, $joiner->allow & DiscordApi::SPEAK);
            }
        }
    }

    /**
     * Discord takes a channel's permissions from its own overwrites, so a
     * public channel inside a locked category is public. The names still have
     * to differ, because the adoption pass tells two channels in one category
     * apart by name, type and parent and nothing else.
     */
    public function test_a_teams_public_channels_share_its_category_without_sharing_a_name(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->for($game)->create(['name' => 'Nightshift']);

        $inCategory = array_filter(
            (new GuildBlueprint($game))->channels(),
            fn (PlannedChannel $channel): bool => $channel->parentKey === 'category:gang:'.$gang->id,
        );

        $this->assertCount(6, $inCategory, 'Two private channels and four public ones.');

        $seen = [];

        foreach ($inCategory as $channel) {
            $fingerprint = $channel->kind->value.'/'.mb_strtolower($channel->name);

            $this->assertArrayNotHasKey($fingerprint, $seen, "Two [{$channel->name}] channels in one category.");

            $seen[$fingerprint] = true;
        }
    }

    public function test_an_unaffiliated_character_gets_a_role_and_a_private_text_channel(): void
    {
        $game = Game::factory()->create();
        $freelancer = Character::factory()->for($game)->create([
            'name' => 'Jack Scanton',
            'role' => CharacterRole::Freelancer,
            'corporation_id' => null,
            'gang_id' => null,
        ]);

        $blueprint = new GuildBlueprint($game);

        $roleKey = GuildBlueprint::characterRoleKey($freelancer);

        $this->assertSame('Jack Scanton', $blueprint->roles()[$roleKey]->name);

        $text = $this->channel($blueprint, GuildBlueprint::independentChannelKey($freelancer, 'text'));

        $this->assertSame('jack-scanton', $text->name);
        $this->assertSame(GuildBlueprint::CATEGORY_INDEPENDENTS, $text->parentKey);

        $targets = $this->overwrites($text);

        $this->assertNotSame(0, $targets[PlannedOverwrite::EVERYONE]->deny & DiscordApi::VIEW_CHANNEL);
        $this->assertNotSame(0, $targets[GuildBlueprint::ROLE_CONTROL]->allow & DiscordApi::VIEW_CHANNEL);
        $this->assertNotSame(0, $targets[$roleKey]->allow & DiscordApi::SEND_MESSAGES);
    }

    public function test_an_unaffiliated_characters_voice_channel_is_public(): void
    {
        $game = Game::factory()->create();
        $government = Character::factory()->for($game)->create([
            'name' => 'HM Government',
            'role' => CharacterRole::Other,
            'corporation_id' => null,
            'gang_id' => null,
        ]);

        $voice = $this->channel(
            new GuildBlueprint($game),
            GuildBlueprint::independentChannelKey($government, 'voice'),
        );

        $this->assertSame(DiscordResourceKind::VoiceChannel, $voice->kind);
        $this->assertSame('HM Government', $voice->name);

        $everyone = $this->overwrites($voice)[PlannedOverwrite::EVERYONE];

        $this->assertNotSame(0, $everyone->allow & DiscordApi::CONNECT);
        $this->assertSame(0, $everyone->deny);
    }

    /**
     * The publication takes the plain slug and the desk takes the suffix, so
     * the channel everyone reads is the one named after the paper.
     */
    public function test_each_press_outlet_publishes_where_everyone_reads_and_only_they_write(): void
    {
        $game = Game::factory()->create();
        $paper = Character::factory()->for($game)->create([
            'name' => 'Business Times',
            'role' => CharacterRole::Press,
            'corporation_id' => null,
            'gang_id' => null,
        ]);

        $blueprint = new GuildBlueprint($game);

        $this->assertSame(
            'business-times-desk',
            $this->channel($blueprint, GuildBlueprint::independentChannelKey($paper, 'text'))->name,
        );

        $publication = $this->channel($blueprint, GuildBlueprint::independentChannelKey($paper, 'publication'));

        $this->assertSame('business-times', $publication->name);

        $targets = $this->overwrites($publication);
        $everyone = $targets[PlannedOverwrite::EVERYONE];

        $this->assertNotSame(0, $everyone->allow & DiscordApi::VIEW_CHANNEL);
        $this->assertNotSame(0, $everyone->deny & DiscordApi::SEND_MESSAGES);
        $this->assertNotSame(
            0,
            $targets[GuildBlueprint::characterRoleKey($paper)]->allow & DiscordApi::SEND_MESSAGES,
            'The paper cannot write in its own channel.',
        );
    }

    /**
     * One per outlet rather than a shared #press: two rival papers must not
     * run their copy together under one masthead.
     */
    public function test_two_press_outlets_get_a_publication_each(): void
    {
        $game = Game::factory()->create();

        foreach (['Business Times', 'Th3 Undergr0und'] as $name) {
            Character::factory()->for($game)->create([
                'name' => $name,
                'role' => CharacterRole::Press,
                'corporation_id' => null,
                'gang_id' => null,
            ]);
        }

        $keys = array_map(
            fn (PlannedChannel $channel): string => $channel->key,
            (new GuildBlueprint($game))->channels(),
        );

        $publications = array_filter($keys, fn (string $key): bool => str_ends_with($key, ':publication'));

        $this->assertCount(2, $publications);
    }

    public function test_a_character_on_a_team_gets_no_channels_of_their_own(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->for($game)->create();
        $runner = Character::factory()->for($game)->for($gang)->create(['role' => CharacterRole::Runner]);

        $blueprint = new GuildBlueprint($game);

        $this->assertArrayNotHasKey(GuildBlueprint::characterRoleKey($runner), $blueprint->roles());

        $keys = array_map(fn (PlannedChannel $channel): string => $channel->key, $blueprint->channels());

        $this->assertNotContains(GuildBlueprint::CATEGORY_INDEPENDENTS, $keys);
        $this->assertNotContains(GuildBlueprint::independentChannelKey($runner, 'text'), $keys);
    }

    public function test_an_unaffiliated_player_is_given_their_own_role(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $paper = Character::factory()->for($game)->create([
            'user_id' => $user->id,
            'role' => CharacterRole::Press,
            'corporation_id' => null,
            'gang_id' => null,
        ]);

        $this->assertSame(
            [GuildBlueprint::characterRoleKey($paper)],
            (new GuildBlueprint($game))->roleKeysForUser($user),
        );
    }

    /**
     * Every permission the blueprint hands out is one the bot asks the server
     * for when it is added.
     *
     * Discord applies only the overwrite bits the caller holds itself and drops
     * the rest in silence, so a fifth permission granted on a channel and not
     * added to the invite would not fail here or anywhere - it would quietly
     * build a channel missing the grant it was given for.
     */
    public function test_nothing_is_granted_that_the_bot_never_asked_the_server_for(): void
    {
        $game = Game::factory()->create();
        Corporation::factory()->for($game)->create();
        Gang::factory()->for($game)->create();
        Character::factory()->for($game)->create([
            'role' => CharacterRole::Press,
            'corporation_id' => null,
            'gang_id' => null,
        ]);

        $granted = 0;

        foreach ((new GuildBlueprint($game))->channels() as $planned) {
            foreach ($planned->overwrites as $overwrite) {
                $granted |= $overwrite->allow;
            }
        }

        $this->assertNotSame(0, $granted, 'No channel granted anything, so this proved nothing.');

        $this->assertSame(
            $granted,
            $granted & DiscordApi::BOT_PERMISSIONS,
            'A channel grants a permission the bot never asked the server for.',
        );
    }
}
