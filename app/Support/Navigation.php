<?php

namespace App\Support;

use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;

/**
 * Which sections of the application a player is offered.
 *
 * A user holds characters rather than a side - somebody running a Freelancer on
 * Saturday may be sitting in a Security chair on Sunday - so what is worth
 * showing them is read off the seats they have claimed in the game that is
 * running, exactly as GamePresenter reads which tier of the Facility board they
 * get. There is no column for it and there should not be one.
 *
 * This decides what is *drawn*, not what is reachable. Every page behind these
 * links already decides for itself what a given viewer may see of it - two
 * tiers on the Facility board, a hand that is only its owner's, a run the
 * Security side cannot see until it starts - and none of that moves here. A
 * link nobody can use is a worse thing than a page somebody reaches by typing
 * its address: the first is the application offering something it will not
 * give, and the second is a curiosity that gets the same filtered payload
 * everybody else gets.
 */
class Navigation
{
    /**
     * Every section, in the order the sidebar draws them.
     *
     * @var list<string>
     */
    public const SECTIONS = [
        'dashboard',
        'facilities',
        'runs',
        'equipment',
        'council',
        'research',
        'shop',
    ];

    /**
     * @return list<string>
     */
    public function sectionsFor(?Game $game, ?User $user): array
    {
        if ($user === null) {
            return [];
        }

        // No game to hold a seat in yet, so there is nothing to read a seat
        // off. The dashboard is what says so.
        if ($game === null) {
            return ['dashboard'];
        }

        // Control sees the lot, here as everywhere. A ruling mid-game must
        // never wait on Control holding the right seat to reach the page.
        if ($user->isControlFor($game)) {
            return self::SECTIONS;
        }

        $seats = $game->characters()
            ->where('user_id', $user->id)
            ->get(['id', 'role', 'council_votes']);

        $corporate = $seats->contains(
            fn (Character $seat): bool => $seat->role->isCorporate(),
        );

        $inTheField = $seats->contains(
            fn (Character $seat): bool => $seat->role->goesOnRuns(),
        );

        // A CEO votes with their Corporation's Political Will; anybody else at
        // the table votes with a bloc Control has written on them. Those two
        // are the whole of who sits there - see characters.council_votes.
        $council = $seats->contains(
            fn (Character $seat): bool => $seat->role === CharacterRole::Ceo
                || $seat->sitsOnCouncil(),
        );

        return array_values(array_filter([
            'dashboard',

            // The public Facility list is public: it is posted in a Discord
            // channel the whole game reads, and a Runner picks their target
            // off it. Nobody is kept from this one.
            'facilities',

            // Both sides of a run. The Runners who walk in, and the Corporate
            // seats whose buildings they are walking into.
            $inTheField || $corporate ? 'runs' : null,

            // A hand is a Runner's. A Corporate seat is refused Equipment
            // outright, so the page would be empty for one.
            $inTheField ? 'equipment' : null,

            $council ? 'council' : null,

            // The research table and the tech tree are a Corporation's, and
            // the tier line inside the page is drawn around the Corporation
            // rather than around the Research seat - the CEO and Security read
            // what the Research player is playing.
            $corporate ? 'research' : null,

            // Two counters: Protection Cards out of a Corporation's Credits,
            // and the market out of a Runner's own. Anybody holding a seat is
            // at one of them.
            $inTheField || $corporate ? 'shop' : null,
        ]));
    }
}
