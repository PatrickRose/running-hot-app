<?php

namespace App\Actions;

use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Binds a signing-in player to any character Control has reserved for their
 * Discord handle.
 *
 * Control builds the roster before the day from the sign-up list, putting each
 * player's Discord handle on their character. The handle is only a claim
 * ticket: the first time that player signs in it is resolved to a permanent
 * user_id, so a later rename cannot detach them from their character.
 */
class ClaimCharactersForUser
{
    /**
     * @return array<int, Character> the characters newly claimed by this user
     */
    public function handle(User $user): array
    {
        $handle = Character::normaliseDiscordUsername($user->discord_username);

        if ($handle === null) {
            return [];
        }

        return DB::transaction(function () use ($user, $handle): array {
            $characters = Character::query()
                ->whereNull('user_id')
                ->whereNotNull('discord_username')
                ->whereRaw('LOWER(discord_username) = ?', [$handle])
                ->whereHas('game', fn ($query) => $query->where('status', '!=', GameStatus::Finished))
                ->lockForUpdate()
                ->get();

            $claimed = [];

            foreach ($characters as $character) {
                $character->forceFill(['user_id' => $user->id])->save();
                $claimed[] = $character;

                Log::info('Character claimed via Discord handle.', [
                    'character_id' => $character->id,
                    'user_id' => $user->id,
                    'handle' => $handle,
                ]);
            }

            return $claimed;
        });
    }
}
