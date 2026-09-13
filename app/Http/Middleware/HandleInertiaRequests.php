<?php

namespace App\Http\Middleware;

use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly GamePresenter $games) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // What a controller said as it redirected back. Ninety-odd of them
            // end in `->with('status', ...)` and none of it reached the page
            // before this, so an install, a reorder or a played equation all
            // happened in silence.
            //
            // Under `flash` rather than as a bare `status`, because the auth
            // pages take a `status` prop of their own and pass it to a panel on
            // the page. Sharing it at the top level would have those messages
            // shown twice, once in the panel and once as a toast.
            // always(), for a sharper reason than it looks. A partial reload
            // does not carry an ordinary shared prop, so the client keeps the
            // one it already had - and the toast listener, which fires on every
            // successful visit, then re-announced the same message on every
            // five-second poll for as long as the page stayed open. Resolved on
            // every response, this is null again the moment the flash has been
            // read, and the toast fires exactly once.
            'flash' => Inertia::always([
                'status' => $request->session()->get('status'),
            ]),
            // The clock, on every page there is.
            //
            // A player needs to know how long is left wherever they are - at
            // the research table, on the Facility board, reading the Council -
            // so the phase is shared rather than passed to the handful of
            // pages that happened to ask for it.
            //
            // always() rather than a plain closure, because the clock has to
            // re-anchor to the server on a *partial* reload too: the research
            // page polls `only: ['research']`, and an ordinary shared prop
            // would be filtered straight out of that response. An always prop
            // ignores the filter, so every poll any page already makes keeps
            // the clock honest for free.
            'phase' => Inertia::always(function (): ?array {
                $game = Game::current();
                $phase = $game?->currentPhase();

                return $phase === null ? null : $this->games->phase($phase);
            }),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
