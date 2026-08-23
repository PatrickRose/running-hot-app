<?php

use App\Enums\CharacterRole;

/**
 * The roster of the real game, applied to every new game by
 * App\Actions\CreateDefaultRoster.
 *
 * This is the starting position only. Every value here is a Tracker that
 * Control moves during play, so nothing in this file is ever read again once a
 * game has been created.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Corporations
    |--------------------------------------------------------------------------
    |
    | Starting Income, Political Will and Credits come from the corporations'
    | briefing documents. Income is the abstraction of a corporation's stock
    | price, so it is a figure Control sets rather than one derived from
    | anything the application models.
    |
    | Each corporation fields the three corporate roles of rulebook 1.3.
    |
    */

    'corporations' => [
        [
            'name' => 'Augmented Nucleotech',
            'income' => 5,
            'political_will' => 5,
            'credits' => 40,
        ],
        [
            'name' => 'Digital Tactical Control',
            'income' => 13,
            'political_will' => 7,
            'credits' => 27,
        ],
        [
            'name' => 'Genetic Equity',
            'income' => 10,
            'political_will' => 10,
            'credits' => 10,
        ],
        [
            'name' => 'Gordon',
            'income' => 13,
            'political_will' => 7,
            'credits' => 22,
        ],
        [
            'name' => 'McCullough Calibrated Mechanical',
            'income' => 12,
            'political_will' => 9,
            'credits' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gangs
    |--------------------------------------------------------------------------
    |
    | Notoriety is tracked per gang rather than per runner (rulebook 2.3.2) and
    | every gang starts unknown.
    |
    | Runner skills are ordered Brawn, Hack, Charisma, Body. The rulebook calls
    | the first skill both "Brute" and "Brawn"; this application standardises on
    | Brawn (see App\Enums\Tracker).
    |
    */

    'gangs' => [
        [
            'name' => 'Facers',
            'notoriety' => 0,
            'runners' => [
                ['name' => 'Con', 'brawn' => 3, 'hack' => 3, 'charisma' => 5, 'body' => 4],
                ['name' => 'Ghost', 'brawn' => 2, 'hack' => 1, 'charisma' => 1, 'body' => 7],
                ['name' => 'Next', 'brawn' => 1, 'hack' => 2, 'charisma' => 3, 'body' => 7],
                ['name' => 'Vampire', 'brawn' => 1, 'hack' => 1, 'charisma' => 6, 'body' => 7],
                ['name' => 'Wicker', 'brawn' => 2, 'hack' => 2, 'charisma' => 3, 'body' => 6],
            ],
        ],
        [
            'name' => 'g33ks',
            'notoriety' => 0,
            'runners' => [
                ['name' => '$0FTW4R3', 'brawn' => 2, 'hack' => 2, 'charisma' => 3, 'body' => 5],
                ['name' => '$TUX', 'brawn' => 3, 'hack' => 0, 'charisma' => 2, 'body' => 6],
                ['name' => 'CYCLE3', 'brawn' => 1, 'hack' => 3, 'charisma' => 3, 'body' => 5],
                ['name' => 'G1T', 'brawn' => 1, 'hack' => 2, 'charisma' => 3, 'body' => 5],
                ['name' => 'Z3R0', 'brawn' => 1, 'hack' => 3, 'charisma' => 3, 'body' => 5],
            ],
        ],
        [
            'name' => 'Dancers',
            'notoriety' => 0,
            'runners' => [
                ['name' => 'Ballet', 'brawn' => 1, 'hack' => 1, 'charisma' => 6, 'body' => 3],
                ['name' => 'Hustle', 'brawn' => 2, 'hack' => 3, 'charisma' => 3, 'body' => 5],
                ['name' => 'Rapper', 'brawn' => 2, 'hack' => 3, 'charisma' => 3, 'body' => 5],
                ['name' => 'Swing', 'brawn' => 3, 'hack' => 2, 'charisma' => 3, 'body' => 5],
                ['name' => 'Tango', 'brawn' => 3, 'hack' => 2, 'charisma' => 3, 'body' => 5],
                ['name' => 'Tap', 'brawn' => 2, 'hack' => 2, 'charisma' => 1, 'body' => 7],
            ],
        ],
        [
            'name' => 'Gruffsters',
            'notoriety' => 0,
            'runners' => [
                ['name' => 'Bitter', 'brawn' => 3, 'hack' => 1, 'charisma' => 3, 'body' => 5],
                ['name' => 'Groucho', 'brawn' => 1, 'hack' => 3, 'charisma' => 3, 'body' => 5],
                ['name' => 'Pale', 'brawn' => 3, 'hack' => 1, 'charisma' => 6, 'body' => 5],
                ['name' => 'Scorer', 'brawn' => 3, 'hack' => 3, 'charisma' => 3, 'body' => 3],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Unaffiliated characters
    |--------------------------------------------------------------------------
    |
    | Freelancers take part in Runs but belong to no gang, so they carry runner
    | skills while Notoriety does not apply to them. The press and the
    | government hold neither.
    |
    */

    'unaffiliated' => [
        ['name' => 'Jack Scanton', 'role' => CharacterRole::Freelancer, 'brawn' => 4, 'hack' => 4, 'charisma' => 3, 'body' => 5],
        ['name' => 'Mandel Reso', 'role' => CharacterRole::Freelancer, 'brawn' => 3, 'hack' => 3, 'charisma' => 3, 'body' => 6],
        ['name' => 'Yale Pirit', 'role' => CharacterRole::Freelancer, 'brawn' => 5, 'hack' => 2, 'charisma' => 3, 'body' => 6],
        ['name' => 'Business Times', 'role' => CharacterRole::Press],
        ['name' => 'Th3 Undergr0und', 'role' => CharacterRole::Press],
        ['name' => 'HM Government', 'role' => CharacterRole::Other],
    ],

    /*
    |--------------------------------------------------------------------------
    | Starting credits
    |--------------------------------------------------------------------------
    |
    | Corporate players spend their Corporation's Credits rather than their own,
    | so their personal purse starts empty.
    |
    */

    'starting_credits' => [
        'corporate' => 0,
        'other' => 5,
    ],

];
