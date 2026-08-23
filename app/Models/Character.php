<?php

namespace App\Models;

use App\Enums\CharacterRole;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A player's character in a game. Freelancers and Runners carry Wounds and
 * Tags; corporate roles generally will not.
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $user_id
 * @property string|null $discord_username
 * @property int|null $corporation_id
 * @property int|null $gang_id
 * @property string $name
 * @property CharacterRole $role
 * @property int $brawn
 * @property int $hack
 * @property int $body
 * @property int $credits
 * @property int $wounds
 * @property int $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id', 'user_id', 'discord_username', 'corporation_id', 'gang_id',
    'name', 'role', 'brawn', 'hack', 'body', 'credits', 'wounds', 'tags',
])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CharacterRole::class,
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

    /** @return BelongsTo<Corporation, $this> */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    /** @return BelongsTo<Gang, $this> */
    public function gang(): BelongsTo
    {
        return $this->belongsTo(Gang::class);
    }

    /** @return MorphMany<TrackerAdjustment, $this> */
    public function trackerAdjustments(): MorphMany
    {
        return $this->morphMany(TrackerAdjustment::class, 'subject');
    }

    /**
     * A runner with Wounds equal to or greater than their Body is incapacitated
     * and instantly leaves a Run (rulebook 3.4.2).
     */
    public function isIncapacitated(): bool
    {
        return $this->wounds >= $this->body;
    }

    public function isClaimed(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Reduce a Discord handle to the form claims are matched on.
     *
     * Control types these off a sign-up sheet, so they arrive with stray
     * whitespace, a leading "@", and inconsistent case. Modern Discord handles
     * are lowercase, but the legacy "Name#1234" form is preserved as typed
     * beyond the case fold, since the discriminator is part of the handle.
     */
    public static function normaliseDiscordUsername(?string $handle): ?string
    {
        $handle = mb_strtolower(trim((string) $handle));
        $handle = ltrim($handle, '@');

        return $handle === '' ? null : $handle;
    }

    /**
     * Keep stored handles in the shape claims are matched on, wherever they are
     * written from: Control, a seeder or a factory.
     */
    protected function setDiscordUsernameAttribute(?string $value): void
    {
        $this->attributes['discord_username'] = self::normaliseDiscordUsername($value);
    }
}
