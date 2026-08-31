<?php

namespace App\Models;

use App\Actions\SeedEquipmentCards;
use App\Actions\SeedFacilityTypes;
use App\Actions\SeedProtectionCards;
use App\Actions\SeedTechnologies;
use App\Enums\DiscordProvisionStatus;
use App\Enums\GameStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property GameStatus $status
 * @property int $stability
 * @property int $civil_unrest
 * @property int $setup_seconds
 * @property int $action_seconds
 * @property int $team_time_seconds
 * @property bool $auto_advance
 * @property string $discord_webhook_url
 * @property string|null $discord_guild_id
 * @property string|null $discord_invite_url
 * @property DiscordProvisionStatus $discord_provision_status
 * @property string|null $discord_provision_message
 * @property Carbon|null $discord_provisioned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'status', 'stability', 'civil_unrest',
    'setup_seconds', 'action_seconds', 'team_time_seconds',
    'auto_advance', 'discord_webhook_url',
    'discord_guild_id', 'discord_invite_url',
    'discord_provision_status', 'discord_provision_message', 'discord_provisioned_at',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * Give every new game the catalogues it is played out of: the Facility
     * types, and the three card lists.
     *
     * A hook rather than a call in the controller so that every route into a
     * game - Control creating one, a seeder, a factory in a test - ends up with
     * them. Control extends or edits them from there.
     *
     * None of these depend on the roster, so they are safe this early. The
     * technologies are the near miss: each tree belongs to a Corporation, and
     * there are none yet, so they are written unattached here and attached once
     * the roster exists. App\Actions\SeedTechnologies runs happily either way.
     */
    protected static function booted(): void
    {
        static::created(function (Game $game): void {
            app(SeedFacilityTypes::class)->handle($game);
            app(SeedProtectionCards::class)->handle($game);
            app(SeedEquipmentCards::class)->handle($game);
            app(SeedTechnologies::class)->handle($game);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'auto_advance' => 'boolean',
            'discord_provision_status' => DiscordProvisionStatus::class,
            'discord_provisioned_at' => 'datetime',
        ];
    }

    /** @return HasMany<Turn, $this> */
    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }

    /** @return HasMany<Corporation, $this> */
    public function corporations(): HasMany
    {
        return $this->hasMany(Corporation::class);
    }

    /** @return HasMany<FacilityType, $this> */
    public function facilityTypes(): HasMany
    {
        return $this->hasMany(FacilityType::class);
    }

    /** @return HasMany<Facility, $this> */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /** @return HasMany<ProtectionCardType, $this> */
    public function protectionCardTypes(): HasMany
    {
        return $this->hasMany(ProtectionCardType::class);
    }

    /** @return HasMany<EquipmentCardType, $this> */
    public function equipmentCardTypes(): HasMany
    {
        return $this->hasMany(EquipmentCardType::class);
    }

    /** @return HasMany<TechnologyType, $this> */
    public function technologyTypes(): HasMany
    {
        return $this->hasMany(TechnologyType::class);
    }

    /**
     * The shared public research deck, plus every Corporation's private one
     * (rulebook 3.2.1).
     *
     * @return HasMany<ResearchCard, $this>
     */
    public function researchCards(): HasMany
    {
        return $this->hasMany(ResearchCard::class);
    }

    /** @return HasMany<ResearchSession, $this> */
    public function researchSessions(): HasMany
    {
        return $this->hasMany(ResearchSession::class);
    }

    /** @return HasMany<ResearchEquation, $this> */
    public function researchEquations(): HasMany
    {
        return $this->hasMany(ResearchEquation::class);
    }

    /** @return HasMany<TechnologyHolding, $this> */
    public function technologyHoldings(): HasMany
    {
        return $this->hasMany(TechnologyHolding::class);
    }

    /** @return HasMany<Gang, $this> */
    public function gangs(): HasMany
    {
        return $this->hasMany(Gang::class);
    }

    /** @return HasMany<Character, $this> */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * The people running this game (rather than playing in it).
     *
     * @return HasMany<ControlMember, $this>
     */
    public function controlMembers(): HasMany
    {
        return $this->hasMany(ControlMember::class);
    }

    /** @return HasMany<TrackerAdjustment, $this> */
    public function trackerAdjustments(): HasMany
    {
        return $this->hasMany(TrackerAdjustment::class);
    }

    /** @return HasMany<DiscordResource, $this> */
    public function discordResources(): HasMany
    {
        return $this->hasMany(DiscordResource::class);
    }

    /** @return HasMany<DiscordMemberSync, $this> */
    public function discordMemberSyncs(): HasMany
    {
        return $this->hasMany(DiscordMemberSync::class);
    }

    /**
     * The game players are in.
     *
     * There is one running game at a time - a session is an evening, and the
     * player pages carry no game in their URL because a player only ever has
     * one. Control's pages all name a game, because Control may be setting the
     * next one up while this one is being played.
     */
    public static function current(): ?self
    {
        /** @var self|null */
        return self::query()
            ->where('status', GameStatus::Running)
            ->latest('id')
            ->first();
    }

    /**
     * Whether this game has a Discord server the application can work on.
     */
    public function hasDiscordGuild(): bool
    {
        return filled($this->discord_guild_id);
    }

    public function currentTurn(): ?Turn
    {
        return $this->turns()->orderByDesc('number')->first();
    }

    /**
     * The phase the game is sitting in, whether it is running or paused.
     */
    public function currentPhase(): ?Phase
    {
        return Phase::query()
            ->whereHas('turn', fn ($query) => $query->where('game_id', $this->id))
            ->whereIn('status', ['running', 'paused'])
            ->orderByDesc('id')
            ->first();
    }

    public function isRunning(): bool
    {
        return $this->status === GameStatus::Running;
    }
}
