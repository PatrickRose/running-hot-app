<?php

namespace App\Http\Controllers\Control;

use App\Actions\ClaimCharactersByEmail;
use App\Actions\ClaimCharactersForUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\UpdateCharacterDiscordRequest;
use App\Http\Requests\Control\UpdateCharacterEmailRequest;
use App\Http\Requests\Control\UpdateCharacterStatsRequest;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CharacterController extends Controller
{
    public function __construct(
        private readonly ClaimCharactersForUser $claimCharacters,
        private readonly ClaimCharactersByEmail $claimByEmail,
    ) {}

    /**
     * Reserve a character for a Discord handle, ahead of the player signing in.
     */
    public function updateDiscord(
        Game $game,
        Character $character,
        UpdateCharacterDiscordRequest $request,
    ): RedirectResponse {
        abort_if($character->game_id !== $game->id, 404);

        $handle = $request->input('discord_username');

        $character->forceFill(['discord_username' => $handle])->save();

        if ($handle === null) {
            return back()->with('status', $character->name.' is no longer reserved.');
        }

        // If that player has already signed in, bind them now rather than
        // making them log out and back in.
        $user = User::query()->whereRaw('LOWER(discord_username) = ?', [$handle])->first();

        if ($user !== null && ! $character->fresh()?->isClaimed()) {
            $this->claimCharacters->handle($user);

            return back()->with('status', $character->name.' claimed by '.$user->name.'.');
        }

        return back()->with('status', $character->name.' reserved for @'.$handle.'.');
    }

    /**
     * Reserve a character for the address a player signed up with.
     *
     * The second claim ticket, and the one Control reaches for when the handle
     * was wrong: the player then finds their own way in at /claim rather than
     * waiting on somebody to work out what their Discord name is.
     */
    public function updateEmail(
        Game $game,
        Character $character,
        UpdateCharacterEmailRequest $request,
    ): RedirectResponse {
        abort_if($character->game_id !== $game->id, 404);

        $email = $request->input('email');

        $character->forceFill(['email' => $email])->save();

        if ($email === null) {
            return back()->with('status', $character->name.' has no email address against them.');
        }

        // If that player has already signed in, bind them now rather than
        // making them go round through /claim - the same courtesy the handle
        // above extends.
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user !== null && ! $character->fresh()?->isClaimed()) {
            $claimed = $this->claimByEmail->handle($user, $email);

            if ($claimed !== []) {
                return back()->with('status', $character->name.' claimed by '.$user->name.'.');
            }
        }

        return back()->with('status', $character->name.' reserved for '.$email.'.');
    }

    /**
     * The four printed stats on a character sheet (rulebook 1.3).
     *
     * Written straight to the row rather than through TrackerService, and that
     * is not an oversight: Brawn, Hack, Charisma and Body are what a character
     * is, not numbers the game moves. Nothing in the rules spends them, so
     * there is no "why did that change?" for a ledger to answer - only Control
     * correcting a roster.
     */
    public function updateStats(
        Game $game,
        Character $character,
        UpdateCharacterStatsRequest $request,
    ): RedirectResponse {
        abort_if($character->game_id !== $game->id, 404);

        $character->forceFill($request->safe()->only([
            'brawn', 'hack', 'charisma', 'body',
        ]))->save();

        return back()->with('status', $character->name.'\'s stats updated.');
    }

    /**
     * Detach the player, freeing the character to be claimed again.
     */
    public function release(Game $game, Character $character): RedirectResponse
    {
        abort_if($character->game_id !== $game->id, 404);

        $character->forceFill(['user_id' => null])->save();

        return back()->with('status', $character->name.' released.');
    }
}
