<?php

namespace App\Actions\Fortify;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Refuse a password sign in when the game is running on Discord alone.
 *
 * The login page already hides the form (see config/running_hot.php), and this
 * is the half that makes the switch mean something: a hidden form whose
 * endpoint still takes credentials is a signpost pretending to be a lock.
 *
 * It is the first pipe in the login pipeline rather than a check inside
 * Fortify::authenticateUsing(), because that callback replaces the credential
 * check outright - so using it would mean reimplementing password verification,
 * remember-me and rehashing here in order to refuse one case. Refusing early
 * and letting Fortify's own actions do the work is the whole point.
 *
 * The message names the way in that does work. A bare "these credentials do not
 * match our records" would have somebody retyping a password that was never
 * going to be looked at.
 */
class EnsureDirectLoginIsEnabled
{
    /**
     * A constant so the test can pin it: the refusal has to keep naming the
     * way in that does work.
     */
    public const REFUSAL = 'Signing in with an email address is turned off for this game. Use Continue with Discord, and confirm with Control that you are set up.';

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (config('running_hot.direct_login')) {
            return $next($request);
        }

        throw ValidationException::withMessages([
            Fortify::username() => __(self::REFUSAL),
        ]);
    }
}
