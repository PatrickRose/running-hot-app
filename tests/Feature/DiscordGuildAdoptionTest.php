<?php

namespace Tests\Feature;

use App\Actions\ProvisionDiscordGuild;
use App\Enums\DiscordResourceKind;
use App\Models\Corporation;
use App\Models\DiscordResource;
use App\Models\Game;
use App\Models\Gang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDiscordGuild;
use Tests\TestCase;

/**
 * Provisioning a server that already has the objects the blueprint asks for.
 *
 * The reconcile loop matches on a recorded snowflake, which is exactly right
 * until there is no record: a rebuilt database, a game recreated for a new
 * session, or a server Control set up by hand against the same plan. Without
 * adoption every one of those grows a second Control role and a second category
 * per team on every single run, which is what a test server looks like after a
 * few months.
 */
class DiscordGuildAdoptionTest extends TestCase
{
    use RefreshDatabase;

    private function gameWithRoster(FakeDiscordGuild $guild): Game
    {
        $game = Game::factory()->create([
            'name' => 'Running Hot',
            'discord_guild_id' => $guild->guildId,
        ]);

        Corporation::factory()->for($game)->create(['name' => 'Aldermarch Dynamics']);
        Gang::factory()->for($game)->create(['name' => 'The Kestrels']);

        return $game;
    }

    public function test_a_role_already_in_the_server_is_adopted_rather_than_duplicated(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $existing = $guild->seedRole('Control');

        $game = $this->gameWithRoster($guild);

        $tally = app(ProvisionDiscordGuild::class)->handle($game);

        $this->assertSame(1, $tally['roles_adopted']);
        $this->assertSame($existing, DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('key', 'role:control')
            ->value('discord_id'));

        // One Control role in the guild, not two.
        $this->assertCount(1, array_filter(
            $guild->roles,
            fn (array $role): bool => $role['name'] === 'Control',
        ));
    }

    public function test_an_adopted_role_is_brought_in_line_with_the_blueprint(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $existing = $guild->seedRole('The Kestrels', ['color' => 0x000000, 'hoist' => false]);

        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        // Adopting is the reconcile the recorded key would have done, so the
        // drift it was carrying is corrected in the same pass.
        $this->assertNotSame(0x000000, $guild->roles[$existing]['color']);
        $this->assertTrue($guild->roles[$existing]['hoist']);
    }

    public function test_the_default_role_is_never_adopted(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // A game whose name matches the guild's default role would otherwise
        // hand @everyone out as if it were a team's.
        $game = Game::factory()->create([
            'name' => '@everyone',
            'discord_guild_id' => $guild->guildId,
        ]);
        Gang::factory()->for($game)->create(['name' => '@everyone']);

        app(ProvisionDiscordGuild::class)->handle($game);

        $recorded = DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('kind', DiscordResourceKind::Role)
            ->pluck('discord_id');

        $this->assertNotContains($guild->guildId, $recorded);
    }

    public function test_a_managed_role_is_never_adopted(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // An integration's own role: Discord will not let anybody rename it or
        // grant it, so adopting one would break every later run.
        $guild->seedRole('The Kestrels', ['managed' => true]);

        $game = $this->gameWithRoster($guild);

        $tally = app(ProvisionDiscordGuild::class)->handle($game);

        $this->assertSame(0, $tally['roles_adopted']);
        $this->assertCount(2, array_filter(
            $guild->roles,
            fn (array $role): bool => $role['name'] === 'The Kestrels',
        ));
    }

    public function test_categories_and_channels_already_in_the_server_are_adopted(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        $category = $guild->seedChannel('The Kestrels', DiscordResourceKind::Category->channelType());
        $text = $guild->seedChannel('the-kestrels', DiscordResourceKind::TextChannel->channelType(), $category);
        $voice = $guild->seedChannel('The Kestrels', DiscordResourceKind::VoiceChannel->channelType(), $category);

        $game = $this->gameWithRoster($guild);
        $gang = $game->gangs()->first();

        $tally = app(ProvisionDiscordGuild::class)->handle($game);

        $this->assertSame(3, $tally['channels_adopted']);

        $recorded = DiscordResource::query()
            ->where('game_id', $game->id)
            ->pluck('discord_id', 'key');

        $this->assertSame($category, $recorded['category:gang:'.$gang->id]);
        $this->assertSame($text, $recorded['channel:gang:'.$gang->id.':text']);
        $this->assertSame($voice, $recorded['channel:gang:'.$gang->id.':voice']);
    }

    public function test_an_adopted_channel_gets_the_permissions_the_blueprint_wants(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // Wide open, which is what a category somebody made by hand looks like.
        $category = $guild->seedChannel('The Kestrels', DiscordResourceKind::Category->channelType());

        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $everyone = collect($guild->channels[$category]['permission_overwrites'])
            ->firstWhere('id', $guild->guildId);

        $this->assertNotNull($everyone, 'An adopted team category must be locked down.');
        $this->assertSame('1024', $everyone['deny']);
    }

    public function test_a_channel_of_the_right_name_in_the_wrong_place_is_not_adopted(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // A text channel named for the gang, but sitting at the top level
        // rather than inside the gang's category. Adopting it would quietly
        // move a channel out of wherever Control had deliberately put it.
        $stray = $guild->seedChannel('the-kestrels', DiscordResourceKind::TextChannel->channelType());

        $game = $this->gameWithRoster($guild);
        $gang = $game->gangs()->first();

        app(ProvisionDiscordGuild::class)->handle($game);

        $this->assertNotSame($stray, DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('key', 'channel:gang:'.$gang->id.':text')
            ->value('discord_id'));
        $this->assertNull($guild->channels[$stray]['parent_id']);
    }

    public function test_a_category_is_not_adopted_by_the_voice_channel_that_shares_its_name(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // A corporation's category and its voice channel are both named for the
        // corporation, so name alone cannot tell them apart.
        $guild->seedChannel('Aldermarch Dynamics', DiscordResourceKind::Category->channelType());

        $game = $this->gameWithRoster($guild);
        $corporation = $game->corporations()->first();

        app(ProvisionDiscordGuild::class)->handle($game);

        $recorded = DiscordResource::query()
            ->where('game_id', $game->id)
            ->pluck('discord_id', 'key');

        $this->assertNotSame(
            $recorded['category:corporation:'.$corporation->id],
            $recorded['channel:corporation:'.$corporation->id.':voice'],
            'Two keys must never be recorded against the same Discord object.',
        );
    }

    public function test_case_is_ignored_when_matching(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // Discord lower-cases a text channel's name for you, so a match that
        // cared about case would miss the very objects this exists to find.
        $existing = $guild->seedRole('the kestrels');

        $game = $this->gameWithRoster($guild);
        $gang = $game->gangs()->first();

        app(ProvisionDiscordGuild::class)->handle($game);

        $this->assertSame($existing, DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('key', 'role:gang:'.$gang->id)
            ->value('discord_id'));
        $this->assertSame('The Kestrels', $guild->roles[$existing]['name']);
    }

    public function test_a_server_provisioned_by_a_forgotten_database_is_reused_wholesale(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $rolesAfterFirst = count($guild->roles);
        $channelsAfterFirst = count($guild->channels);

        // Everything the application knew is gone — a rebuilt database, or the
        // same server handed to a game set up again from scratch. The guild is
        // untouched, and provisioning must recognise its own work in it.
        DiscordResource::query()->where('game_id', $game->id)->delete();

        $tally = app(ProvisionDiscordGuild::class)->handle($game->fresh());

        $this->assertSame(0, $tally['roles_created'], 'The roles are all already there.');
        $this->assertSame(0, $tally['channels_created'], 'The channels are all already there.');
        $this->assertSame($rolesAfterFirst, count($guild->roles));
        $this->assertSame($channelsAfterFirst, count($guild->channels));
    }

    public function test_an_existing_webhook_is_adopted_rather_than_joined_by_a_second(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $this->assertCount(1, $guild->webhooks);

        DiscordResource::query()->where('game_id', $game->id)->delete();

        app(ProvisionDiscordGuild::class)->handle($game->fresh());

        $this->assertCount(1, $guild->webhooks, 'The announcements channel must not collect a second webhook.');
    }
}
