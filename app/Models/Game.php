<?php

namespace App\Models;

use App\Actions\SeedFacilityTypes;
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
     * Give every new game the starting Facility type catalogue.
     *
     * A hook rather than a call in the controller so that every route into a
     * game - Control creating one, a seeder, a factory in a test - ends up with
     * a catalogue. Control extends or edits it from there.
     */
    protected static function booted(): void
    {
        static::created(function (Game $game): void {
            app(SeedFacilityTypes::class)->handle($game);
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
