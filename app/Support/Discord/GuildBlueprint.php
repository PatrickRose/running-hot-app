<?php

namespace App\Support\Discord;

use App\Actions\ProvisionDiscordGuild;
use App\Actions\ProvisionFacilityChannels;
use App\Enums\CharacterRole;
use App\Enums\DiscordResourceKind;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * What a game's Discord guild should look like.
 *
 * Deliberately pure: it reads the roster and returns a description, touching
 * neither Discord nor the database. Everything about the shape of a server is
 * therefore assertable in a unit test, and applying it
 * ({@see ProvisionDiscordGuild}) is only the reconcile loop.
 *
 * The roster has to exist first. A blueprint for a game with no corporations
 * and no gangs is just the Control role and the common channels, which is
 * correct rather than an error: Control can provision early and re-run once the
 * roster is filled in.
 */
class GuildBlueprint
{
    public const ROLE_CONTROL = 'role:control';

    public const CATEGORY_COMMON = 'category:common';

    public const CHANNEL_ANNOUNCEMENTS = 'channel:common:announcements';

    public const CHANNEL_FACILITY_LIST = 'channel:common:facility-list';

    /**
     * Where the characters who belong to no team live.
     *
     * Deliberately not private: it holds the press's publications and a
     * public voice room for each of its members, and only the private text
     * channels inside it are locked. A category with no overwrites of its own
     * is what {@see self::CATEGORY_COMMON} already is.
     *
     * Not to be confused with {@see self::CATEGORY_PLOT_FACILITIES}, which is
     * the buildings nobody owns rather than the people. The two are separate
     * keys and separate categories; they share only the word.
     */
    public const CATEGORY_INDEPENDENTS = 'category:independents';

    /**
     * How many public voice rooms a faction gets.
     *
     * Three because a faction is negotiating with several people at once for
     * most of a turn, and one room means whoever got there first owns it: a
     * Runner who wants a word has to wait out a Council deal. They are named
     * with a number rather than a purpose, since what a room is for changes
     * every fifteen minutes.
     */
    public const PUBLIC_VOICE_ROOMS = 3;

    /**
     * The category holding every Plot Facility's pair of channels.
     *
     * One category for all of them rather than one each, because a Plot
     * Facility has no team to be grouped under - what they share is that
     * Control built them. Locked to Control alone, exactly as a Corporation's
     * category is locked to its Corporation: the Runners attacking one are let
     * in per member when their run starts, which is the Run's business.
     *
     * Nothing to do with {@see self::CATEGORY_INDEPENDENTS}, which houses the
     * characters who belong to no team.
     */
    public const CATEGORY_PLOT_FACILITIES = 'category:plot-facilities';

    /**
     * What that category is called in the guild. Named for what players can
     * see - a Facility belonging to nobody in the roster - rather than for what
     * Control is doing with it.
     */
    public const PLOT_FACILITIES_CATEGORY_NAME = 'Independent Facilities';

    /**
     * Discord's limit on how many channels one category may hold.
     *
     * A Corporation's category holds its two team channels, its four public
     * ones and a pair for each Facility, so it runs out at 22 Facilities — well
     * past anything the game's economy allows one Corporation to build. The
     * Independents category holds two or three channels a head and so runs out
     * at around twenty unaffiliated characters, against the six the roster
     * ships. Recorded rather than guarded against for that reason.
     */
    public const MAX_CHANNELS_PER_CATEGORY = 50;

    /**
     * Team colours, indexed deterministically off the team's name so a role
     * keeps its colour across reconciles and two teams rarely collide.
     *
     * @var array<int, int>
     */
    private const TEAM_COLOURS = [
        0x1ABC9C, 0x3498DB, 0x9B59B6, 0xE91E63, 0xF1C40F,
        0xE67E22, 0x2ECC71, 0x11806A, 0x206694, 0x71368A,
    ];

    /**
     * The corporate roles that get a guild-wide role of their own.
     *
     * Global rather than per-corporation so a channel can address every CEO or
     * every Security lead at once, which is the cross-corp shape the game
     * actually needs. A player holds their corporation's role as well.
     *
     * @return array<int, CharacterRole>
     */
    public static function functionRoles(): array
    {
        return [CharacterRole::Ceo, CharacterRole::Security, CharacterRole::Research];
    }

    public function __construct(private readonly Game $game) {}

    /**
     * @return array<string, PlannedRole>
     */
    public function roles(): array
    {
        $roles = [
            self::ROLE_CONTROL => new PlannedRole(
                key: self::ROLE_CONTROL,
                name: 'Control',
                colour: 0xC0392B,
            ),
        ];

        foreach (self::functionRoles() as $role) {
            $roles[self::functionRoleKey($role)] = new PlannedRole(
                key: self::functionRoleKey($role),
                name: $role->label(),
                colour: 0x95A5A6,
                // Not hoisted: a player is grouped in the member list under
                // their team, and hoisting both would list them twice.
                hoist: false,
            );
        }

        foreach ($this->corporations() as $corporation) {
            $roles[self::corporationRoleKey($corporation)] = new PlannedRole(
                key: self::corporationRoleKey($corporation),
                name: $corporation->name,
                colour: self::colourFor($corporation->name),
            );
        }

        foreach ($this->gangs() as $gang) {
            $roles[self::gangRoleKey($gang)] = new PlannedRole(
                key: self::gangRoleKey($gang),
                name: $gang->name,
                colour: self::colourFor($gang->name),
            );
        }

        // A role per unaffiliated character, because their channels have to be
        // permissioned against something and there is no team to hang them on.
        // A role rather than a per-member overwrite for the reason a Runner's
        // key to a Facility is the other way round: who the press are is a
        // standing fact about the guild, not something true for ten minutes.
        //
        // Hoisted like a team's, since it is the only grouping these players
        // have — the member list is where Control finds the Government player.
        foreach ($this->independents() as $character) {
            $roles[self::characterRoleKey($character)] = new PlannedRole(
                key: self::characterRoleKey($character),
                name: $character->name,
                colour: self::colourFor($character->name),
            );
        }

        return $roles;
    }

    /**
     * Categories and channels, in the order they must be created: a channel's
     * parent category has to exist before the channel does.
     *
     * @return array<int, PlannedChannel>
     */
    public function channels(): array
    {
        return [
            ...$this->commonChannels(),
            ...$this->controlChannels(),
            ...$this->functionChannels(),
            ...$this->teamChannels(),
            ...$this->independentChannels(),
            ...$this->facilityChannels(),
        ];
    }

    /**
     * Channels everyone in the game can see.
     *
     * @return array<int, PlannedChannel>
     */
    private function commonChannels(): array
    {
        return [
            new PlannedChannel(
                key: self::CATEGORY_COMMON,
                kind: DiscordResourceKind::Category,
                name: $this->game->name,
            ),
            new PlannedChannel(
                key: self::CHANNEL_ANNOUNCEMENTS,
                kind: DiscordResourceKind::TextChannel,
                name: 'announcements',
                parentKey: self::CATEGORY_COMMON,
                // Read-only for players: this is where the phase clock posts,
                // and a conversation in it would bury the call to action.
                overwrites: [
                    new PlannedOverwrite(PlannedOverwrite::EVERYONE, allow: DiscordApi::VIEW_CHANNEL, deny: DiscordApi::SEND_MESSAGES),
                    new PlannedOverwrite(self::ROLE_CONTROL, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES),
                ],
                topic: 'Turn and phase announcements. Posted by Control.',
            ),
            new PlannedChannel(
                key: 'channel:common:general',
                kind: DiscordResourceKind::TextChannel,
                name: 'general',
                parentKey: self::CATEGORY_COMMON,
                overwrites: [
                    new PlannedOverwrite(PlannedOverwrite::EVERYONE, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES),
                ],
                topic: 'Everyone, out of character. Rules questions go here.',
            ),
            new PlannedChannel(
                key: self::CHANNEL_FACILITY_LIST,
                kind: DiscordResourceKind::TextChannel,
                name: 'facility-list',
                parentKey: self::CATEGORY_COMMON,
                overwrites: [
                    new PlannedOverwrite(PlannedOverwrite::EVERYONE, allow: DiscordApi::VIEW_CHANNEL, deny: DiscordApi::SEND_MESSAGES),
                    new PlannedOverwrite(self::ROLE_CONTROL, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES),
                ],
                topic: 'Who owns what. Stays empty until Facilities are built.',
            ),
        ];
    }

    /**
     * @return array<int, PlannedChannel>
     */
    private function controlChannels(): array
    {
        $onlyControl = [
            new PlannedOverwrite(PlannedOverwrite::EVERYONE, deny: DiscordApi::VIEW_CHANNEL),
            new PlannedOverwrite(self::ROLE_CONTROL, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES | DiscordApi::CONNECT | DiscordApi::SPEAK),
        ];

        return [
            new PlannedChannel(
                key: 'category:control',
                kind: DiscordResourceKind::Category,
                name: 'Control',
                overwrites: $onlyControl,
            ),
            new PlannedChannel(
                key: 'channel:control:text',
                kind: DiscordResourceKind::TextChannel,
                name: 'control-room',
                parentKey: 'category:control',
                overwrites: $onlyControl,
                topic: 'Organisers only.',
            ),
            new PlannedChannel(
                key: 'channel:control:voice',
                kind: DiscordResourceKind::VoiceChannel,
                name: 'Control Room',
                parentKey: 'category:control',
                overwrites: $onlyControl,
            ),
        ];
    }

    /**
     * A channel per global corporate function, which is what those roles are
     * for: every CEO in one room, without their Security reading it.
     *
     * @return array<int, PlannedChannel>
     */
    private function functionChannels(): array
    {
        if ($this->corporations()->isEmpty()) {
            return [];
        }

        $channels = [
            new PlannedChannel(
                key: 'category:functions',
                kind: DiscordResourceKind::Category,
                name: 'Cross-Corporate',
                overwrites: [
                    new PlannedOverwrite(PlannedOverwrite::EVERYONE, deny: DiscordApi::VIEW_CHANNEL),
                    new PlannedOverwrite(self::ROLE_CONTROL, allow: DiscordApi::VIEW_CHANNEL),
                ],
            ),
        ];

        foreach (self::functionRoles() as $role) {
            $channels[] = new PlannedChannel(
                key: 'channel:function:'.$role->value,
                kind: DiscordResourceKind::TextChannel,
                name: Str::slug($role->label()),
                parentKey: 'category:functions',
                overwrites: [
                    new PlannedOverwrite(PlannedOverwrite::EVERYONE, deny: DiscordApi::VIEW_CHANNEL),
                    new PlannedOverwrite(self::ROLE_CONTROL, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES),
                    new PlannedOverwrite(self::functionRoleKey($role), allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES),
                ],
                topic: sprintf('Every %s, across all corporations.', $role->label()),
            );
        }

        return $channels;
    }

    /**
     * A category per corporation and per gang: the team's own locked text and
     * voice channels, and the public ones anybody may walk into.
     *
     * @return array<int, PlannedChannel>
     */
    private function teamChannels(): array
    {
        $channels = [];

        foreach ($this->corporations() as $corporation) {
            $channels = [...$channels, ...$this->channelsForTeam(
                'corporation:'.$corporation->id,
                $corporation->name,
                self::corporationRoleKey($corporation),
            )];
        }

        foreach ($this->gangs() as $gang) {
            $channels = [...$channels, ...$this->channelsForTeam(
                'gang:'.$gang->id,
                $gang->name,
                self::gangRoleKey($gang),
            )];
        }

        return $channels;
    }

    /**
     * A text and voice channel for every Facility, where its Runs will happen.
     *
     * They sit in the Corporation's own category, beside the two channels its
     * players talk in, so everything belonging to a Corporation is in one place.
     *
     * Private to Control and the owning Corporation. The Runners attacking a
     * Facility are added when a Run starts, which is the Run's business: they
     * choose their target in Secret (rulebook 3.4.1), so access before the
     * Action phase resolves would leak who is hitting what.
     *
     * @return array<int, PlannedChannel>
     */
    private function facilityChannels(): array
    {
        $channels = [];

        foreach ($this->corporations() as $corporation) {
            foreach ($corporation->facilities()->orderBy('name')->get() as $facility) {
                $channels = [...$channels, ...self::channelsForFacility($facility)];
            }
        }

        // The Plot Facilities' shared category, and only when there is one to
        // put in it: a game with no plot buildings should not grow an empty
        // category in its channel list.
        $plot = $this->game->facilities()->plot()->orderBy('name')->get();

        if ($plot->isNotEmpty()) {
            $channels[] = new PlannedChannel(
                key: self::CATEGORY_PLOT_FACILITIES,
                kind: DiscordResourceKind::Category,
                name: self::PLOT_FACILITIES_CATEGORY_NAME,
                overwrites: self::controlOnlyOverwrites(),
            );

            foreach ($plot as $facility) {
                $channels = [...$channels, ...self::channelsForFacility($facility)];
            }
        }

        return $channels;
    }

    /**
     * The pair of channels one Facility should have.
     *
     * Static so that a Facility built mid-game can be given its channels
     * without rebuilding the whole blueprint, and so both paths agree on the
     * shape — {@see ProvisionFacilityChannels}.
     *
     * The name carries the Facility's name and nothing else. Its type is public
     * (it is in #facility-list), but a channel name is a poor place for it, and
     * rulebook 3.4.2 makes what is installed Secret regardless.
     *
     * @return array<int, PlannedChannel>
     */
    public static function channelsForFacility(Facility $facility): array
    {
        $overwrites = self::facilityOverwrites($facility);
        $parentKey = self::categoryKeyForFacility($facility);

        return [
            new PlannedChannel(
                key: self::facilityChannelKey($facility, 'text'),
                kind: DiscordResourceKind::TextChannel,
                name: Str::slug($facility->name),
                parentKey: $parentKey,
                overwrites: $overwrites,
                topic: $facility->name.' — Runs against this Facility happen here.',
            ),
            new PlannedChannel(
                key: self::facilityChannelKey($facility, 'voice'),
                kind: DiscordResourceKind::VoiceChannel,
                name: $facility->name,
                parentKey: $parentKey,
                overwrites: $overwrites,
            ),
        ];
    }

    /**
     * The category holding everything a Corporation owns: its two team channels,
     * its four public ones, and a pair for each of its Facilities.
     */
    public static function corporationCategoryKey(Corporation $corporation): string
    {
        return 'category:corporation:'.$corporation->id;
    }

    /**
     * The category a Facility's pair of channels hangs off: its Corporation's,
     * or the shared one every Plot Facility sits in.
     */
    public static function categoryKeyForFacility(Facility $facility): string
    {
        $corporation = $facility->corporation;

        return $corporation === null
            ? self::CATEGORY_PLOT_FACILITIES
            : self::corporationCategoryKey($corporation);
    }

    /**
     * What that category is called, for the one path that has to create it
     * without a whole blueprint in hand ({@see ProvisionFacilityChannels}).
     */
    public static function categoryNameForFacility(Facility $facility): string
    {
        return $facility->isPlotFacility()
            ? self::PLOT_FACILITIES_CATEGORY_NAME
            : $facility->corporation->name;
    }

    /**
     * Who may read a Facility's channels before a run starts.
     *
     * A Corporation's Facility opens to its own team; a Plot Facility opens to
     * nobody but Control, because nobody in the roster owns it. Both deny
     *
     * @everyone, and both are widened per member when the Runners hitting the
     * Facility are let in for the length of their run.
     *
     * @return array<int, PlannedOverwrite>
     */
    public static function facilityOverwrites(Facility $facility): array
    {
        $corporation = $facility->corporation;

        return $corporation === null
            ? self::controlOnlyOverwrites()
            : self::teamOverwrites(self::corporationRoleKey($corporation));
    }

    /**
     * @return array<int, PlannedOverwrite>
     */
    private static function controlOnlyOverwrites(): array
    {
        return [
            new PlannedOverwrite(PlannedOverwrite::EVERYONE, deny: DiscordApi::VIEW_CHANNEL),
            new PlannedOverwrite(self::ROLE_CONTROL, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES | DiscordApi::CONNECT | DiscordApi::SPEAK),
        ];
    }

    public static function facilityChannelKey(Facility $facility, string $kind): string
    {
        return 'channel:facility:'.$facility->id.':'.$kind;
    }

    /**
     * Locked to everyone, open to Control and one team.
     *
     * @return array<int, PlannedOverwrite>
     */
    private static function teamOverwrites(string $roleKey): array
    {
        return [
            new PlannedOverwrite(PlannedOverwrite::EVERYONE, deny: DiscordApi::VIEW_CHANNEL),
            new PlannedOverwrite(self::ROLE_CONTROL, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES | DiscordApi::CONNECT | DiscordApi::SPEAK),
            new PlannedOverwrite($roleKey, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES | DiscordApi::CONNECT | DiscordApi::SPEAK),
        ];
    }

    /**
     * Open to the whole game: read and write, or join and speak.
     *
     * Only @everyone is named, because a grant to everybody is a grant to
     * Control as well — #general in the common category is the same shape. A
     * channel the blueprint locks says so in its own overwrites, and a
     * category's denial never reaches one of these.
     *
     * @return array<int, PlannedOverwrite>
     */
    private static function publicOverwrites(DiscordResourceKind $kind): array
    {
        return [
            new PlannedOverwrite(
                PlannedOverwrite::EVERYONE,
                allow: $kind === DiscordResourceKind::VoiceChannel
                    ? DiscordApi::VIEW_CHANNEL | DiscordApi::CONNECT | DiscordApi::SPEAK
                    : DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES,
            ),
        ];
    }

    /**
     * One team's channels: the two only they can see, and the four anybody can.
     *
     * The category is locked and the public channels sit inside it anyway, which
     * is not a contradiction — Discord computes a channel's permissions from its
     * own overwrites, so a category's denial only reaches a channel that has
     * nothing to say for itself. Keeping them together is the same reasoning
     * that puts a Corporation's Facility channels here: everything a team owns
     * is in one place, and a player looking for the Kestrels finds the whole of
     * the Kestrels.
     *
     * The public text channel is where the rest of the game comes to talk to a
     * team, which is the thing the private one cannot be: a faction that only
     * had a locked room had to be approached by DM, and Control could not see
     * any of it. It is named with a suffix rather than taking the plain slug
     * because Discord would otherwise have two channels of the same name in the
     * same category, which the adoption pass could not tell apart.
     *
     * @return array<int, PlannedChannel>
     */
    private function channelsForTeam(string $slug, string $name, string $roleKey): array
    {
        $overwrites = self::teamOverwrites($roleKey);

        $channels = [
            new PlannedChannel(
                key: 'category:'.$slug,
                kind: DiscordResourceKind::Category,
                name: $name,
                overwrites: $overwrites,
            ),
            new PlannedChannel(
                key: 'channel:'.$slug.':text',
                kind: DiscordResourceKind::TextChannel,
                name: Str::slug($name),
                parentKey: 'category:'.$slug,
                overwrites: $overwrites,
                topic: $name.' — team channel.',
            ),
            new PlannedChannel(
                key: 'channel:'.$slug.':voice',
                kind: DiscordResourceKind::VoiceChannel,
                name: $name,
                parentKey: 'category:'.$slug,
                overwrites: $overwrites,
            ),
            new PlannedChannel(
                key: self::publicTeamTextKey($slug),
                kind: DiscordResourceKind::TextChannel,
                name: Str::slug($name).'-public',
                parentKey: 'category:'.$slug,
                overwrites: self::publicOverwrites(DiscordResourceKind::TextChannel),
                topic: $name.' — open to everyone. Their own room is private.',
            ),
        ];

        for ($room = 1; $room <= self::PUBLIC_VOICE_ROOMS; $room++) {
            $channels[] = new PlannedChannel(
                key: self::publicTeamVoiceKey($slug, $room),
                kind: DiscordResourceKind::VoiceChannel,
                name: $name.' '.$room,
                parentKey: 'category:'.$slug,
                overwrites: self::publicOverwrites(DiscordResourceKind::VoiceChannel),
            );
        }

        return $channels;
    }

    /**
     * The characters who belong to neither a Corporation nor a gang: the two
     * press outlets, HM Government and the Freelancers.
     *
     * Read off the absence of a team rather than off a list of roles, so a
     * character Control invents mid-game — a second Government department, the
     * Runner Representative of the Council's own agenda card — is housed without
     * new code. Naming HM Government here would be the same mistake
     * `characters.council_votes` already avoids.
     *
     * Each gets a private text channel, which is the thing they had no way of
     * having before: a gang has a locked room to plan in and a Freelancer had
     * nowhere at all. The voice channel is public because the whole of a
     * Freelancer's game is being available to whoever wants to hire them.
     *
     * @return array<int, PlannedChannel>
     */
    private function independentChannels(): array
    {
        $independents = $this->independents();

        if ($independents->isEmpty()) {
            return [];
        }

        $channels = [
            new PlannedChannel(
                key: self::CATEGORY_INDEPENDENTS,
                kind: DiscordResourceKind::Category,
                name: 'Independents',
            ),
        ];

        foreach ($independents as $character) {
            $roleKey = self::characterRoleKey($character);
            $slug = Str::slug($character->name);
            $isPress = $character->role === CharacterRole::Press;

            $channels[] = new PlannedChannel(
                key: self::independentChannelKey($character, 'text'),
                kind: DiscordResourceKind::TextChannel,
                // A press outlet has two text channels, and the plain slug goes
                // to the one everybody reads — that is the masthead. So the
                // private one takes the suffix, and only theirs needs one.
                name: $isPress ? $slug.'-desk' : $slug,
                parentKey: self::CATEGORY_INDEPENDENTS,
                overwrites: self::teamOverwrites($roleKey),
                topic: $character->name.' — private. Control reads this too.',
            );

            if ($isPress) {
                $channels[] = new PlannedChannel(
                    key: self::independentChannelKey($character, 'publication'),
                    kind: DiscordResourceKind::TextChannel,
                    name: $slug,
                    parentKey: self::CATEGORY_INDEPENDENTS,
                    // One per outlet rather than a shared #press: Business Times
                    // and Th3 Undergr0und are rival papers, and a single feed
                    // would run their copy together under one masthead.
                    overwrites: [
                        new PlannedOverwrite(PlannedOverwrite::EVERYONE, allow: DiscordApi::VIEW_CHANNEL, deny: DiscordApi::SEND_MESSAGES),
                        new PlannedOverwrite(self::ROLE_CONTROL, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES),
                        new PlannedOverwrite($roleKey, allow: DiscordApi::VIEW_CHANNEL | DiscordApi::SEND_MESSAGES),
                    ],
                    topic: 'What '.$character->name.' publishes. Everyone reads it; only they write in it.',
                );
            }

            $channels[] = new PlannedChannel(
                key: self::independentChannelKey($character, 'voice'),
                kind: DiscordResourceKind::VoiceChannel,
                name: $character->name,
                parentKey: self::CATEGORY_INDEPENDENTS,
                overwrites: self::publicOverwrites(DiscordResourceKind::VoiceChannel),
            );
        }

        return $channels;
    }

    /**
     * The role keys a given player should hold in this game.
     *
     * Control membership is a property of the login rather than of a character,
     * so a Control member with no character still gets the Control role. It is
     * asked of this game rather than in general, because a seat on one game's
     * Control team is not Control of the server next door.
     *
     * @return array<int, string>
     */
    public function roleKeysForUser(User $user): array
    {
        $keys = [];

        if ($user->isControlFor($this->game)) {
            $keys[] = self::ROLE_CONTROL;
        }

        $characters = $this->game->characters()
            ->where('user_id', $user->id)
            ->get();

        foreach ($characters as $character) {
            if ($character->corporation_id !== null) {
                $keys[] = 'role:corporation:'.$character->corporation_id;
            }

            if ($character->gang_id !== null) {
                $keys[] = 'role:gang:'.$character->gang_id;
            }

            if (in_array($character->role, self::functionRoles(), true)) {
                $keys[] = self::functionRoleKey($character->role);
            }

            if (self::isIndependent($character)) {
                $keys[] = self::characterRoleKey($character);
            }
        }

        return array_values(array_unique($keys));
    }

    public static function functionRoleKey(CharacterRole $role): string
    {
        return 'role:function:'.$role->value;
    }

    public static function corporationRoleKey(Corporation $corporation): string
    {
        return 'role:corporation:'.$corporation->id;
    }

    public static function gangRoleKey(Gang $gang): string
    {
        return 'role:gang:'.$gang->id;
    }

    /**
     * The role an unaffiliated character holds. Nobody else has one: a Runner is
     * addressed through their gang and a CEO through their Corporation.
     */
    public static function characterRoleKey(Character $character): string
    {
        return 'role:character:'.$character->id;
    }

    /**
     * @param  string  $kind  'text', 'publication' or 'voice'
     */
    public static function independentChannelKey(Character $character, string $kind): string
    {
        return 'channel:character:'.$character->id.':'.$kind;
    }

    public static function publicTeamTextKey(string $slug): string
    {
        return 'channel:'.$slug.':public';
    }

    public static function publicTeamVoiceKey(string $slug, int $room): string
    {
        return 'channel:'.$slug.':public-voice:'.$room;
    }

    /**
     * Whether this character belongs to no team, and so is housed in the
     * Independents category under a role of their own.
     */
    public static function isIndependent(Character $character): bool
    {
        return $character->corporation_id === null && $character->gang_id === null;
    }

    /**
     * @return Collection<int, Corporation>
     */
    private function corporations()
    {
        return $this->game->corporations()->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Gang>
     */
    private function gangs()
    {
        return $this->game->gangs()->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Character>
     */
    private function independents()
    {
        return $this->game->characters()
            ->whereNull('corporation_id')
            ->whereNull('gang_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * A team's colour, indexed off its name so the same team is the same colour
     * everywhere the application draws it.
     *
     * Public because anything the application shows about a team should match
     * the colour of the Discord role players already see against their own
     * name - {@see FacilityListEmbed} colours a
     * Corporation's embed with it.
     */
    public static function colourFor(string $name): int
    {
        $index = (int) hexdec(mb_substr(md5($name), 0, 8)) % count(self::TEAM_COLOURS);

        return self::TEAM_COLOURS[$index];
    }
}
