<?php

namespace App\Http\Controllers\Control;

use App\Actions\ClaimCharactersForUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\UpdateCharacterDiscordRequest;
use App\Http\Requests\Control\UpdateCharacterStatsRequest;
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
