<?php

namespace App\Http\Controllers\Control;

use App\Actions\ClaimControlSeatsForUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\StoreControlMemberRequest;
use App\Jobs\SyncDiscordRoles;
use App\Models\ControlMember;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * The game's Control team: who, besides whoever owns the deployment, is running
 * it.
 *
 * A seat is named by Discord handle and claimed at sign in, so an organiser
 * needs nothing but the Discord account they are already running the game from.
 */
class ControlMemberController extends Controller
{
    public function __construct(private readonly ClaimControlSeatsForUser $claimSeats) {}

    public function store(Game $game, StoreControlMemberRequest $request): RedirectResponse
    {
        $handle = $request->input('discord_username');

        $game->controlMembers()->create(['discord_username' => $handle]);

        // If they have already signed in, bind them now rather than making them
        // log out and back in to become Control.
        $user = User::query()->whereRaw('LOWER(discord_username) = ?', [$handle])->first();

        if ($user !== null) {
            $this->claimSeats->handle($user);

            // Their Control role in this game's server is part of the seat.
            SyncDiscordRoles::dispatch($user->id);

            return back()->with('status', $user->name.' is now Control for this game.');
        }

        return back()->with('status', '@'.$handle.' will be Control when they sign in.');
    }

    /**
     * Take a seat back.
     *
     * The Discord role goes with it, which is why the sync runs on the way out
     * as well: a sync only ever adds or removes this game's own roles, so it is
     * the same call in both directions.
     */
    public function destroy(Game $game, ControlMember $controlMember): RedirectResponse
    {
        abort_if($controlMember->game_id !== $game->id, 404);

        $userId = $controlMember->user_id;
        $label = $controlMember->user?->name ?? '@'.$controlMember->discord_username;

        $controlMember->delete();

        if ($userId !== null) {
            SyncDiscordRoles::dispatch($userId);
        }

        return back()->with('status', $label.' is no longer Control for this game.');
    }
}
