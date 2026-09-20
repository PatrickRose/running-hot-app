<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * The front door. There is no landing page: everybody who opens this
     * application signs in, so `/` sends a visitor to the login form and
     * somebody already signed in to their dashboard.
     *
     * A controller rather than a closure because `route:cache` refuses to
     * serialise a closure, which would break the one command a deployment runs.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user() === null ? 'login' : 'dashboard');
    }
}
