<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Phase;
use App\Services\TurnEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PhaseController extends Controller
{
    public function __construct(private readonly TurnEngine $engine) {}

    public function start(Game $game): RedirectResponse
    {
        return $this->run(
            fn () => $this->engine->start($game, request()->user()),
            'Turn 1 has begun.',
        );
    }

    public function advance(Game $game): RedirectResponse
    {
        return $this->run(function () use ($game) {
            $this->engine->advance($this->currentPhase($game), request()->user());
        }, 'Phase advanced.');
    }

    public function pause(Game $game): RedirectResponse
    {
        return $this->run(
            fn () => $this->engine->pause($this->currentPhase($game)),
            'Clock paused.',
        );
    }

    public function resume(Game $game): RedirectResponse
    {
        return $this->run(
            fn () => $this->engine->resume($this->currentPhase($game)),
            'Clock resumed.',
        );
    }

    public function extend(Game $game, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seconds' => ['required', 'integer', 'min:-3600', 'max:3600', 'not_in:0'],
        ]);

        return $this->run(
            fn () => $this->engine->extend($this->currentPhase($game), (int) $validated['seconds']),
            'Clock adjusted.',
        );
    }

    protected function currentPhase(Game $game): Phase
    {
        $phase = $game->currentPhase();

        if ($phase === null) {
            throw ValidationException::withMessages([
                'phase' => 'This game has no phase in progress.',
            ]);
        }

        return $phase;
    }

    /**
     * Turn the engine's guard clauses into validation errors the dashboard can
     * show, rather than a 500.
     *
     * @param  callable(): mixed  $operation
     */
    protected function run(callable $operation, string $status): RedirectResponse
    {
        try {
            $operation();
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'phase' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', $status);
    }
}
