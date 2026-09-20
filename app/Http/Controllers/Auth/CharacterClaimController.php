<?php

namespace App\Http\Controllers\Auth;

use App\Actions\ClaimCharactersByEmail;
use App\Http\Controllers\Controller;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "I signed up to this game with this email address."
 *
 * The way back in for somebody Control could not reach by Discord handle. It
 * is reached from the login page, and it works from both sides of the sign in
 * because both cases are real:
 *
 * - A visitor names their address, and is sent through the Discord sign in
 *   that already exists. The address rides the session across the round trip
 *   and DiscordController redeems it once there is an account to bind.
 * - Somebody already signed in - because signing in worked, it just found
 *   them nothing - is bound on the spot. There is no reason to send them
 *   round through Discord again to learn something the session already knows.
 *
 * The session key is deliberately not the character: what Control edits
 * between one step and the next is the roster, and looking the address up
 * again at the far end is how a correction made in the meantime is picked up.
 */
class CharacterClaimController extends Controller
{
    /**
     * The key the address waits under while Discord is being visited.
     */
    public const PENDING = 'claim.email';

    public function __construct(private readonly ClaimCharactersByEmail $claim) {}

    public function create(): Response
    {
        return Inertia::render('auth/claim');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = Character::normaliseEmail($validated['email']);
        $reserved = $this->claim->reservedFor($email);

        if ($reserved->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => 'No seat is reserved for that address. Check with Control that they have it, and that it is the one you signed up with.',
            ]);
        }

        // Every seat on that address is already somebody's. Saying so is better
        // than a silent success: the usual cause is the player having signed in
        // once already, and the rest is Control's to sort out.
        if ($reserved->every(fn (Character $character): bool => $character->isClaimed())) {
            $mine = $reserved->every(
                fn (Character $character): bool => $character->user_id === $request->user()?->id,
            );

            throw ValidationException::withMessages([
                'email' => $mine
                    ? 'You already hold every seat reserved for that address.'
                    : 'Every seat reserved for that address has already been claimed. Speak to Control.',
            ]);
        }

        $user = $request->user();

        if ($user === null) {
            $request->session()->put(self::PENDING, $email);

            return to_route('auth.discord');
        }

        $claimed = $this->claim->handle($user, $email);

        return to_route('dashboard')->with('status', self::announce($claimed));
    }

    /**
     * What to tell somebody who has just been bound to their seats.
     *
     * @param  array<int, Character>  $claimed
     */
    public static function announce(array $claimed): string
    {
        if ($claimed === []) {
            return 'That address is on the roster, but the seat was claimed before you got here. Speak to Control.';
        }

        $names = implode(', ', array_map(
            fn (Character $character): string => $character->name,
            $claimed,
        ));

        return 'You are playing '.$names.'.';
    }
}
