<?php

namespace App\Http\Controllers\Control;

use App\Enums\PhaseType;
use App\Enums\Tracker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\AdjustTrackerRequest;
use App\Models\Character;
use App\Models\Game;
use App\Services\TrackerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class TrackerController extends Controller
{
    public function __construct(private readonly TrackerService $trackers) {}

    public function store(Game $game, AdjustTrackerRequest $request): RedirectResponse
    {
        $subject = $request->subject();
        $tracker = $request->tracker();
        $value = (int) $request->integer('value');
        $reason = $request->string('reason')->toString() ?: null;

        abort_if($subject === null, 404);

        $adjustment = $request->string('mode')->toString() === 'set'
            ? $this->trackers->set($subject, $tracker, $value, $reason, $request->user())
            : $this->trackers->adjust($subject, $tracker, $value, $reason, $request->user());

        return back()->with('status', sprintf(
            '%s is now %d.',
            $tracker->label(),
            $adjustment->value_after,
        ));
    }

    /**
     * Buy off a single Tag for 3 Credits during Team Time (rulebook 2.3.2).
     *
     * Control can always bypass the phase and cost restrictions using the raw
     * tracker controls; this endpoint encodes the rule as written.
     */
    public function removeTag(Game $game, Character $character): RedirectResponse
    {
        abort_if($character->game_id !== $game->id, 404);

        $phase = $game->currentPhase();

        if ($phase?->type !== PhaseType::TeamTime) {
            throw ValidationException::withMessages([
                'tags' => 'Tags may only be bought off during Team Time.',
            ]);
        }

        if ($character->tags < 1) {
            throw ValidationException::withMessages([
                'tags' => $character->name.' has no Tags to remove.',
            ]);
        }

        if ($character->credits < 3) {
            throw ValidationException::withMessages([
                'tags' => $character->name.' cannot afford the 3 Credits.',
            ]);
        }

        $reason = sprintf('Turn %d Team Time: bought off a Tag', $phase->turn->number);

        $this->trackers->adjust($character, Tracker::CharacterCredits, -3, $reason, request()->user());
        $this->trackers->adjust($character, Tracker::Tags, -1, $reason, request()->user());

        return back()->with('status', $character->name.' removed a Tag for 3 Credits.');
    }
}
