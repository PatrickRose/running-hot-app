<?php

namespace App\Actions;

use App\Models\ControlMember;
use App\Models\User;
use App\Support\DiscordHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Binds a signing-in organiser to any Control seat reserved for their handle.
 *
 * The same claim ticket a character uses, for the other half of the roster:
 * Control names the people running the game by Discord handle before the day,
 * and the seat resolves to a permanent user_id the first time each of them
 * signs in. A later rename cannot then take Control away mid-game.
 *
 * Unlike a character, a seat is claimable in a finished game too: reviewing the
 * game afterwards is Control's, and there is no play left to hand anybody.
 */
class ClaimControlSeatsForUser
{
    /**
     * @return array<int, ControlMember> the seats newly claimed by this user
     */
    public function handle(User $user): array
    {
        $handle = DiscordHandle::normalise($user->discord_username);

        if ($handle === null) {
            return [];
        }

        return DB::transaction(function () use ($user, $handle): array {
            $seats = ControlMember::query()
                ->whereNull('user_id')
                ->whereNotNull('discord_username')
                ->whereRaw('LOWER(discord_username) = ?', [$handle])
                // Someone already sitting on this game's Control team keeps the
                // seat they have; a second row for them would break the one
                // seat per person the table promises.
                ->whereDoesntHave('game.controlMembers', fn ($query) => $query->where('user_id', $user->id))
                ->lockForUpdate()
                ->get();

            $claimed = [];
            $games = [];

            foreach ($seats as $seat) {
                // One seat per person per game, so a game that has already
                // handed them one this pass is done: a second row for the same
                // handle is a duplicate rather than a second organiser.
                if (isset($games[$seat->game_id])) {
                    continue;
                }

                $games[$seat->game_id] = true;

                $seat->forceFill(['user_id' => $user->id])->save();
                $claimed[] = $seat;

                Log::info('Control seat claimed via Discord handle.', [
                    'control_member_id' => $seat->id,
                    'game_id' => $seat->game_id,
                    'user_id' => $user->id,
                    'handle' => $handle,
                ]);
            }

            return $claimed;
        });
    }
}
