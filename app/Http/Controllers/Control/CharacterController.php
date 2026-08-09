<?php

namespace App\Http\Controllers\Control;

use App\Actions\ClaimCharactersForUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\UpdateCharacterDiscordRequest;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CharacterController extends Controller
{
    public function __construct(private readonly ClaimCharactersForUser $claimCharacters) {}

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
     * Detach the player, freeing the character to be claimed again.
     */
    public function release(Game $game, Character $character): RedirectResponse
    {
        abort_if($character->game_id !== $game->id, 404);

        $character->forceFill(['user_id' => null])->save();

        return back()->with('status', $character->name.' released.');
    }
}
