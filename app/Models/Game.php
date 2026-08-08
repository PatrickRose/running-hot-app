<?php

namespace App\Models;

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
 * @property string|null $discord_webhook_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'status', 'stability', 'civil_unrest',
    'setup_seconds', 'action_seconds', 'team_time_seconds',
    'auto_advance', 'discord_webhook_url',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'auto_advance' => 'boolean',
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
