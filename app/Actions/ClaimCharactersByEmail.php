<?php

namespace App\Actions;

use App\Enums\GameStatus;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Binds a player to a character by the email address they signed up with.
 *
 * The second claim ticket, and the one that rescues the first. A seat is
 * normally reserved for a Discord handle and resolved the first time that
 * player signs in - but Control types those off a sign-up list, people rename
 * themselves between signing up and turning up, and a handle Control never got
 * leaves somebody signing in to a dashboard with no characters on it.
 *
 * So the address is matched instead, and the Discord account that comes back
 * from the sign in is written onto the character: the `user_id` binds them
 * permanently, and the handle goes on too so the Control panel shows the seat
 * as linked and every later sign in claims it the ordinary way.
 *
 * It only ever takes a seat nobody holds. That is the whole of what stops the
 * address being a way to walk off with somebody else's character, because the
 * address itself proves nothing - see the note on trust in CLAUDE.md.
 */
class ClaimCharactersByEmail
{
    /**
     * Every character in a live game reserved for this address, claimed or not.
     *
     * @return Collection<int, Character>
     */
    public function reservedFor(?string $email): Collection
    {
        $email = Character::normaliseEmail($email);

        if ($email === null) {
            return new Collection;
        }

        return Character::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereHas('game', fn ($query) => $query->where('status', '!=', GameStatus::Finished))
            ->with('game:id,name')
            ->get();
    }

    /**
     * Bind whichever of those nobody holds yet.
     *
     * @return array<int, Character> the characters newly claimed by this user
     */
    public function handle(User $user, ?string $email): array
    {
        $email = Character::normaliseEmail($email);

        if ($email === null) {
            return [];
        }

        return DB::transaction(function () use ($user, $email): array {
            $characters = Character::query()
                ->whereNull('user_id')
                ->whereNotNull('email')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereHas('game', fn ($query) => $query->where('status', '!=', GameStatus::Finished))
                ->lockForUpdate()
                ->get();

            $claimed = [];

            foreach ($characters as $character) {
                $character->forceFill([
                    'user_id' => $user->id,
                    // Only if the sign in brought one. A password account has
                    // no handle, and writing null over what Control reserved
                    // would throw away the thing they typed in.
                    'discord_username' => $user->discord_username ?? $character->discord_username,
                ])->save();

                $claimed[] = $character;

                Log::info('Character claimed via email address.', [
                    'character_id' => $character->id,
                    'user_id' => $user->id,
                    'email' => $email,
                ]);
            }

            return $claimed;
        });
    }
}
