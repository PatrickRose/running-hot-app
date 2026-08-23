<?php

namespace App\Models;

use App\Enums\DiscordSyncStatus;
use Database\Factories\DiscordMemberSyncFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The outcome of the last role push for one player in one game.
 *
 * @property int $id
 * @property int $game_id
 * @property int $user_id
 * @property DiscordSyncStatus $status
 * @property string|null $message
 * @property array<int, string>|null $role_ids
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['game_id', 'user_id', 'status', 'message', 'role_ids', 'synced_at'])]
class DiscordMemberSync extends Model
{
    /** @use HasFactory<DiscordMemberSyncFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DiscordSyncStatus::class,
            'role_ids' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
