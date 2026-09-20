<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Dark unless the reader has said otherwise. The game is played in
        // the evening and the application is themed off a neon sign, so dark
        // is the design rather than a preference - 'system' as a default had
        // half the table on a white screen. 'system' is still a setting; it is
        // just no longer what you get for saying nothing.
        View::share('appearance', $request->cookie('appearance') ?? 'dark');

        return $next($request);
    }
}
