<?php

namespace Tests\Feature;

use App\Actions\GrantRunChannelAccess;
use App\Actions\ProvisionDiscordGuild;
use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\ProtectionKind;
use App\Jobs\SyncRunChannelAccess;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\Run;
use App\Models\Turn;
use App\Models\User;
use App\Services\Dice;
use App\Services\Discord\DiscordApi;
use App\Services\RunEngine;
use App\Services\TurnEngine;
use App\Support\Discord\GuildBlueprint;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeDice;
use Tests\Support\FakeDiscordGuild;
use Tests\TestCase;

/**
 * The Runners get into the Facility they are hitting, and out again
 * (rulebook 3.4).
 *
 * Every Facility already has a private text and voice channel locked to Control
 * and the Corporation that owns it. This is the half that lets the attackers in
 * — and the timing is the rule being kept: targets are chosen in Secret (3.4.1),
 * so a Runner appearing in the channel before they go in would tell the whole
 * server who was hitting what.
 */
class RunChannelAccessTest extends TestCase
{
    use RefreshDatabase;

    private FakeDiscordGuild $discord;

    private Game $game;

    private Turn $turn;

    private Corporation $corporation;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Dice::class, new FakeDice);

        $this->discord = (new FakeDiscordGuild)->bind();

        $this->game = Game::factory()->create([
            'status' => GameStatus::Running,
            'discord_guild_id' => $this->discord->guildId,
        ]);
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

        /** @var Turn $turn */
        $turn = $this->game->currentTurn();
        $this->turn = $turn;

        $this->corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);

        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        $this->facility = Facility::factory()
            ->for($this->corporation)
            ->for($type)
            ->create(['name' => 'Attercliffe Yard']);

        FacilityProtectionCard::factory()->create([
            'facility_id' => $this->facility->id,
            'protection_card_type_id' => ProtectionCardType::factory()
                ->ofKind(ProtectionKind::Physical)
                ->create(['game_id' => $this->game->id])->id,
            'kind' => ProtectionKind::Physical,
            'position' => 1,
        ]);
    }

    /**
     * The Runners are let in as the group goes in, and not a moment before.
     */
    public function test_going_in_lets_the_runners_into_the_facilitys_channels(): void
    {
        $this->assertChannelsExist();
        [$leader, $mate] = [$this->runner('111'), $this->runner('222')];

        $run = app(RunEngine::class)->submit($this->turn, $this->facility, $leader, [$mate->id]);

        // Submitted is not in. Nothing has been granted yet.
        $this->assertSame([], $this->overwriteIds('text'));

        app(RunEngine::class)->begin($run);

        $this->assertSame(['111', '222'], $this->overwriteIds('text'));
        $this->assertSame(['111', '222'], $this->overwriteIds('voice'));
    }

    /**
     * Read, write, connect and speak: a Run is a conversation under time
     * pressure and the voice channel is the point of having one.
     */
    public function test_a_runner_may_read_write_and_talk_in_there(): void
    {
        $this->assertChannelsExist();
        $run = app(RunEngine::class)->submit($this->turn, $this->facility, $this->runner('111'));
        app(RunEngine::class)->begin($run);

        $overwrite = collect($this->overwrites('voice'))->firstWhere('id', '111');

        $this->assertSame(DiscordApi::OVERWRITE_MEMBER, $overwrite['type']);
        $this->assertSame(
            (string) GrantRunChannelAccess::RUNNER_PERMISSIONS,
            $overwrite['allow'],
        );
        $this->assertSame('0', $overwrite['deny']);
    }

    public function test_the_run_ending_takes_them_back_out(): void
    {
        $this->assertChannelsExist();
        $leader = $this->runner('111');

        $run = app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $leader)
        );

        $this->assertSame(['111'], $this->overwriteIds('text'));

        // The last Runner walking away ends the run.
        app(RunEngine::class)->leave($run, $leader);

        $this->assertSame([], $this->overwriteIds('text'));
        $this->assertSame([], $this->overwriteIds('voice'));
    }

    /**
     * "If the end of phase is called and you have not yet been successful then
     * your run is treated as unsuccessful" (3.4.5) — and the keys come back
     * with it.
     */
    public function test_the_action_phase_ending_takes_them_out_too(): void
    {
        $this->assertChannelsExist();
        app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $this->runner('111'))
        );

        $this->assertSame(['111'], $this->overwriteIds('text'));

        app(RunEngine::class)->failUnfinishedRuns($this->turn);

        $this->assertSame([], $this->overwriteIds('text'));
    }

    /**
     * A run submitted and never begun was never granted anything, so failing it
     * must not send a request per Runner per channel for nothing.
     */
    public function test_a_run_that_never_went_in_is_not_revoked(): void
    {
        $this->assertChannelsExist();
        app(RunEngine::class)->submit($this->turn, $this->facility, $this->runner('111'));

        Queue::fake();
        app(RunEngine::class)->failUnfinishedRuns($this->turn);

        Queue::assertNotPushed(SyncRunChannelAccess::class);
    }

    /**
     * A Runner who walks away keeps their access until the run is over. They
     * already know the target, so nothing new leaks — and the rulebook is
     * neutral about leaving, so taking them out of the conversation would be a
     * punishment it never printed.
     */
    public function test_a_runner_who_leaves_keeps_their_access_until_the_run_ends(): void
    {
        $this->assertChannelsExist();
        [$leader, $mate] = [$this->runner('111'), $this->runner('222')];

        $run = app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $leader, [$mate->id])
        );

        app(RunEngine::class)->leave($run, $mate);

        $this->assertSame(['111', '222'], $this->overwriteIds('text'));
    }

    /**
     * A character nobody has claimed, or a player who has never signed in with
     * Discord, has no snowflake to grant anything to. Normal rather than a
     * failure: a character is set up before anybody claims it.
     */
    public function test_a_runner_with_no_discord_account_is_skipped(): void
    {
        $this->assertChannelsExist();
        $leader = $this->runner('111');
        $unclaimed = Character::factory()->create([
            'game_id' => $this->game->id,
            'role' => CharacterRole::Runner,
        ]);

        app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $leader, [$unclaimed->id])
        );

        $this->assertSame(['111'], $this->overwriteIds('text'));
    }

    /**
     * The Facility's channels are missing when a Discord outage caught the
     * requisition. The Control panel already badges that as fixable, so a run
     * must not fall over on it.
     */
    public function test_a_facility_with_no_channels_does_not_break_a_run(): void
    {
        $this->forgetChannels();

        $run = app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $this->runner('111'))
        );

        $this->assertNotNull($run->started_at);
        $this->assertSame(0, $this->discord->countCalls('put', '/permissions/'));
    }

    /**
     * Provisioning reconciles; it never resets. A provision run mid-Facility
     * re-sends every channel's permission table, and the Runners' own
     * overwrites have to survive that — otherwise the group is locked out
     * halfway down a stack.
     */
    public function test_a_provision_run_does_not_lock_the_runners_out_mid_run(): void
    {
        $this->seat(CharacterRole::Security);
        $this->assertChannelsExist();

        app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $this->runner('111'))
        );

        $this->assertSame(['111'], $this->overwriteIds('text'));

        app(ProvisionDiscordGuild::class)->handle($this->game->refresh());

        $this->assertContains('111', $this->overwriteIds('text'));
        // And the Corporation's own role is still on the channel.
        $this->assertGreaterThan(1, count($this->overwrites('text')));
    }

    /**
     * Fail-soft, and for a sharp reason: the sync driver runs this inline, so a
     * throw would come back out of begin() and stop the group going in.
     */
    public function test_a_discord_outage_does_not_stop_a_group_going_in(): void
    {
        $this->assertChannelsExist();

        // Every channel permission call answers 500.
        $this->discord->refuseRoleGrants = false;
        Http::fake([
            'discord.com/api/*/permissions/*' => Http::response(
                ['message' => 'Server Error'],
                500,
            ),
            'discord.com/api/*' => Http::response([], 200),
        ]);

        $run = app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $this->runner('111'))
        );

        $this->assertNotNull($run->refresh()->started_at);
    }

    public function test_nothing_is_attempted_without_a_bot_token(): void
    {
        $this->assertChannelsExist();
        config()->set('services.discord.bot_token', null);

        app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $this->runner('111'))
        );

        $this->assertSame(0, $this->discord->countCalls('put', '/permissions/'));
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * Check the Facility got its pair of channels.
     *
     * Creating one dispatches SyncFacilityChannels, and the sync queue driver
     * runs it inline - so by the time a test starts, the channels are already
     * there. Asserting it rather than making them is what keeps this test
     * honest about where they come from.
     */
    private function assertChannelsExist(): void
    {
        foreach (['text', 'voice'] as $kind) {
            $this->assertNotNull(
                $this->game->discordResources()
                    ->where('key', GuildBlueprint::facilityChannelKey($this->facility, $kind))
                    ->first(),
                "The Facility has no {$kind} channel on record.",
            );
        }
    }

    /**
     * Take the Facility's channels off the record, which is what a Discord
     * outage during the requisition leaves behind.
     */
    private function forgetChannels(): void
    {
        $this->game->discordResources()
            ->whereIn('key', [
                GuildBlueprint::facilityChannelKey($this->facility, 'text'),
                GuildBlueprint::facilityChannelKey($this->facility, 'voice'),
            ])
            ->delete();
    }

    private function runner(string $discordId): Character
    {
        $user = User::factory()->create(['discord_id' => $discordId]);

        return Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
            'brawn' => 2,
            'hack' => 2,
        ]);
    }

    private function seat(CharacterRole $role): User
    {
        $user = User::factory()->create(['discord_id' => '900'.$role->value]);

        $this->corporation->characters()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'name' => $this->corporation->name.' '.$role->label(),
            'role' => $role,
        ]);

        return $user;
    }

    /**
     * The overwrites on one of the Facility's channels.
     *
     * @return array<int, array<string, mixed>>
     */
    private function overwrites(string $kind): array
    {
        $resource = $this->game->discordResources()
            ->where('key', GuildBlueprint::facilityChannelKey($this->facility, $kind))
            ->first();

        if ($resource === null) {
            return [];
        }

        return $this->discord->channels[$resource->discord_id]['permission_overwrites'] ?? [];
    }

    /**
     * Just the member overwrites, as Discord ids, sorted so an assertion reads.
     *
     * @return array<int, string>
     */
    private function overwriteIds(string $kind): array
    {
        $ids = collect($this->overwrites($kind))
            ->filter(fn (array $overwrite): bool => (int) ($overwrite['type'] ?? 0) === DiscordApi::OVERWRITE_MEMBER)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        sort($ids);

        return $ids;
    }
}
