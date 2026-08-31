<?php

namespace App\Models;

use App\Support\DiscordHandle;
use Database\Factories\ControlMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A seat on a game's Control team.
 *
 * Control membership was an account-wide flag granted from the console, which
 * says nothing about which game somebody is running. A seat says it for one
 * game, and is reserved by Discord handle so an organiser only has to sign in.
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $user_id
 * @property string|null $discord_username
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['game_id', 'user_id', 'discord_username'])]
class ControlMember extends Model
{
    /** @use HasFactory<ControlMemberFactory> */
    use HasFactory;

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

    public function isClaimed(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Keep stored handles in the shape claims are matched on, wherever they are
     * written from: Control, a seeder or a factory.
     */
    protected function setDiscordUsernameAttribute(?string $value): void
    {
        $this->attributes['discord_username'] = DiscordHandle::normalise($value);
    }
}
