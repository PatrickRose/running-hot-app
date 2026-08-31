<?php

namespace App\Http\Controllers\Auth;

use App\Actions\ClaimCharactersForUser;
use App\Actions\ClaimControlSeatsForUser;
use App\Http\Controllers\Controller;
use App\Jobs\SyncDiscordRoles;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Discord is where the game is played, so it is also how players sign in.
 *
 * Accounts are matched on the immutable Discord snowflake, never on the
 * username, because Discord usernames can be changed at any time.
 */
class DiscordController extends Controller
{
    public function __construct(
        private readonly ClaimCharactersForUser $claimCharacters,
        private readonly ClaimControlSeatsForUser $claimControlSeats,
    ) {}

    public function redirect(): SymfonyRedirectResponse
    {
        // The Discord provider already asks for "identify" and "email" by
        // default, which is everything we need to create an account.
        return Socialite::driver('discord')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (Throwable) {
            return to_route('login')->withErrors([
                'discord' => 'We could not complete the Discord sign in. Please try again.',
            ]);
        }

        $user = User::query()->where('discord_id', $discordUser->getId())->first();

        // Fall back to matching an existing account by email so that a Control
        // member who registered with a password can link their Discord.
        if ($user === null && filled($discordUser->getEmail())) {
            $user = User::query()->where('email', $discordUser->getEmail())->first();
        }

        if ($user === null) {
            $user = new User([
                'name' => $discordUser->getNickname() ?: $discordUser->getName() ?: 'Runner',
                'email' => $discordUser->getEmail() ?: sprintf('%s@discord.local', $discordUser->getId()),
            ]);

            $user->password = Str::password(32);
            $user->email_verified_at = now();
        }

        $user->forceFill([
            'discord_id' => $discordUser->getId(),
            'discord_username' => $discordUser->getNickname() ?: $discordUser->getName(),
            'discord_avatar' => $discordUser->getAvatar(),
        ])->save();

        // Bind the player to whatever Control reserved for their handle. Doing
        // this on every sign in, not just the first, means a handle added to the
        // roster after someone has already logged in still reaches them.
        $claimed = $this->claimCharacters->handle($user);

        // And to whatever Control seats they have been named on, which is how
        // an organiser becomes Control of a game without anyone touching the
        // console.
        $seats = $this->claimControlSeats->handle($user);

        // Hand out this player's Discord roles for every game they are in.
        // Queued rather than inline: signing in must not wait on Discord, and
        // must not fail if Discord is having a bad day. Like the claim above
        // this runs on every sign in, so a roster change made after someone has
        // already logged in still reaches them the next time they do.
        SyncDiscordRoles::dispatch($user->id);

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        $status = [];

        if ($claimed !== []) {
            $names = implode(', ', array_map(fn (Character $character): string => $character->name, $claimed));

            $status[] = 'You are playing '.$names.'.';
        }

        if ($seats !== []) {
            $games = implode(', ', array_map(
                fn (ControlMember $seat): string => $seat->game->name,
                $seats,
            ));

            $status[] = 'You are Control for '.$games.'.';
        }

        if ($status === []) {
            return to_route('dashboard');
        }

        return to_route('dashboard')->with('status', implode(' ', $status));
    }
}
