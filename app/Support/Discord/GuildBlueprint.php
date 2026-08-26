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
     * Discord's limit on how many channels one category may hold.
     *
     * A Corporation's category holds its two team channels plus a pair for
     * each Facility, so this is the point at which its 25th Facility stops
     * fitting — 24 is the most it can hold. Far beyond a game whose
     * Corporations open with five, but the number is here so that a change to
     * the shape of these channels has to reckon with it rather than discover it
     * as a 400 from Discord mid-game.
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
     * A private category per corporation and per gang, holding a text and a
     * voice channel. This is the shape the Discord bot used for facilities:
     *
     * @everyone cannot see it, Control and the team can.
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
     * Discord allows 50 channels per category and the team pair takes two of
     * them, so this holds 24 Facilities per Corporation.
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
        $corporation = $facility->corporation;
        $overwrites = self::teamOverwrites(self::corporationRoleKey($corporation));
        $parentKey = self::corporationCategoryKey($corporation);

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
     * The category holding everything a Corporation owns: its two team channels
     * and a pair for each of its Facilities.
     */
    public static function corporationCategoryKey(Corporation $corporation): string
    {
        return 'category:corporation:'.$corporation->id;
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
     * @return array<int, PlannedChannel>
     */
    private function channelsForTeam(string $slug, string $name, string $roleKey): array
    {
        $overwrites = self::teamOverwrites($roleKey);

        return [
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
        ];
    }

    /**
     * The role keys a given player should hold in this game.
     *
     * Control membership is a property of the login rather than of a character,
     * so a Control member with no character still gets the Control role.
     *
     * @return array<int, string>
     */
    public function roleKeysForUser(User $user): array
    {
        $keys = [];

        if ($user->isControl()) {
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
