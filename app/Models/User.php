<?php

namespace App\Models;

use App\Enums\CharacterRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_control
 * @property string|null $discord_id
 * @property string|null $discord_username
 * @property string|null $discord_avatar
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Memoised: isControl is asked on every Control request, often more than
     * once, and the answer cannot change inside one.
     */
    private ?bool $controlAnywhere = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_control' => 'boolean',
        ];
    }

    /** @return HasMany<Character, $this> */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /** @return HasMany<ControlMember, $this> */
    public function controlMemberships(): HasMany
    {
        return $this->hasMany(ControlMember::class);
    }

    /**
     * Members of the Control team may drive the turn clock and edit trackers.
     *
     * True for anyone Control anywhere: the account-wide flag, or a seat on
     * some game's Control team. Which games they may actually touch is
     * isControlFor, checked per game on the routes that name one.
     */
    public function isControl(): bool
    {
        return $this->controlAnywhere ??= $this->isControlEverywhere()
            || $this->controlMemberships()->exists();
    }

    /**
     * The account-wide flag, which is Control of every game there will ever be.
     *
     * Deliberately not mass assignable: it is granted from the console, and is
     * for whoever owns the deployment. Everyone else running a game is named on
     * that game's Control team instead.
     */
    public function isControlEverywhere(): bool
    {
        // Cast defensively: a freshly created model may not have the column
        // hydrated, in which case the attribute is missing rather than false.
        return (bool) $this->is_control;
    }

    /**
     * Whether this account is Control of one particular game.
     */
    public function isControlFor(Game $game): bool
    {
        return $this->isControlEverywhere()
            || $this->controlMemberships()->where('game_id', $game->id)->exists();
    }

    /**
     * The Corporation this account plays for in a game, if any.
     *
     * A player is bound to a Corporation by holding one of its seats rather
     * than by a column, so this reads their claimed characters. Naming a role
     * asks the narrower question - "whose Research player is this?" - which is
     * what the research sub-game needs, since a CEO reads their Corporation's
     * research and does not play it.
     */
    public function corporationIn(Game $game, ?CharacterRole $role = null): ?Corporation
    {
        $characters = $game->characters()
            ->where('user_id', $this->id)
            ->whereNotNull('corporation_id')
            ->with('corporation')
            ->get();

        if ($role !== null) {
            return $characters->firstWhere('role', $role)?->corporation;
        }

        return $characters
            ->first(fn (Character $character): bool => $character->role->isCorporate())
            ?->corporation;
    }
}
