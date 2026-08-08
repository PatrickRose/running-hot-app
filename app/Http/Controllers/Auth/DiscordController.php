<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('discord')
            ->scopes(['identify', 'email'])
            ->redirect();
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

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return to_route('dashboard');
    }
}
