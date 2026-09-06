<?php

use App\Enums\CharacterRole;
use App\Enums\ProtectionKind;
use App\Support\FacilityTypeBlueprint;

/**
 * The roster of the real game, applied to every new game by
 * App\Actions\CreateDefaultRoster.
 *
 * This is the starting position only. Every value here is a Tracker that
 * Control moves during play, so nothing in this file is ever read again once a
 * game has been created.
 *
 * The exception is the first entry, which is not roster at all: it is read by
 * the demo seeder rather than by a game, and it lives here because a seeder
 * cannot read the environment once config is cached.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Demo game Control team
    |--------------------------------------------------------------------------
    |
    | Discord handles DemoGameSeeder seats on the demo game's Control team, so
    | that signing in with Discord locally lands on the Control panel rather
    | than on a player's dashboard. Comma or space separated; the seeder takes
    | the same thing as an argument, which wins over this.
    |
    | Empty is the normal setting, and the shipped one: the demo game then has
    | only the password login the seeder prints. A seat here is claimed the
    | first time that handle signs in, exactly as a character is, so it may
    | name someone who has never logged in.
    |
    */

    'demo_control_discord' => env('DEMO_CONTROL_DISCORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Council
    |--------------------------------------------------------------------------
    |
    | Failing to take your seat at the Council has "a negative impact on your
    | Political Will" (rulebook 3.1.2), and that is the whole of what the rules
    | say: no figure, and no mechanism. So this is only what Control's form is
    | pre-filled with. Nothing charges it on its own - Control marks the seat
    | absent and applies the penalty, and may type any figure over this one.
    |
    */

    'council' => [
        'absence_penalty' => env('COUNCIL_ABSENCE_PENALTY', 1),
    ],

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
    | 'facilities' is what the corporation opens with. The counts and types come
    | from the briefings, and they are not uniform: the difference is mechanical
    | rather than decorative - DTC's second Security Facility widens every one
    | of its stacks, Gordon's three Corporate Facilities make it the only
    | corporation that can store six technologies per Facility, and Genetic
    | Equity's three Research Facilities are its whole strategy. Applied by
    | App\Actions\CreateDefaultFacilities.
    |
    | The names are flavour rather than briefing data: Procatorion was bought
    | out of land near Sheffield, so they are places there. Rename them freely,
    | here or in the Control panel - a Facility's name is what players will call
    | it all game, and nothing keys off it.
    |
    */

    'corporations' => [
        [
            'name' => 'Augmented Nucleotech',
            'facilities' => [
                ['type' => FacilityTypeBlueprint::RESEARCH, 'name' => 'Kelham Island Laboratories'],
                ['type' => FacilityTypeBlueprint::CORPORATE, 'name' => 'Nucleotech House'],
                ['type' => FacilityTypeBlueprint::SECURITY, 'name' => 'Neepsend Armoury'],
                ['type' => FacilityTypeBlueprint::POWER, 'name' => 'Blackburn Meadows Station'],
            ],
            // From the briefing. ANT holds its own five cards rather than the
            // five the other Corporations do - they are distinct cards, not
            // ANT's names for them - plus the Orc everybody has.
            'protection_cards' => [
                'PS009' => 4, // Orc
                'PS015' => 4, // Öryggissveit
                'PS016' => 4, // Takkaborðið
                'PS017' => 4, // Öryggisluggari
                'PS018' => 3, // Vélfærafræði sporðdreka
                'PS020' => 3, // Engill
            ],
            'income' => 5,
            'political_will' => 5,
            'credits' => 40,
        ],
        [
            'name' => 'Digital Tactical Control',
            'facilities' => [
                ['type' => FacilityTypeBlueprint::RESEARCH, 'name' => 'Shirecliffe Laboratories'],
                ['type' => FacilityTypeBlueprint::CORPORATE, 'name' => 'Tactical House'],
                ['type' => FacilityTypeBlueprint::SECURITY, 'name' => 'Wincobank Keep'],
                ['type' => FacilityTypeBlueprint::SECURITY, 'name' => 'Grimesthorpe Barracks'],
                ['type' => FacilityTypeBlueprint::ARMS, 'name' => 'Brightside Arsenal'],
            ],
            'protection_cards' => [
                'PS009' => 4, // Orc
                'PS003' => 4, // Security team
                'PS005' => 4, // Keypad
                'PS006' => 4, // Security shutter
                'PS019' => 3, // Roboscorpion
                'PS013' => 3, // Angel
            ],
            'income' => 13,
            'political_will' => 7,
            'credits' => 27,
        ],
        [
            'name' => 'Genetic Equity',
            'facilities' => [
                ['type' => FacilityTypeBlueprint::RESEARCH, 'name' => 'Crookes Institute'],
                ['type' => FacilityTypeBlueprint::RESEARCH, 'name' => 'Broomhall Genome Wing'],
                ['type' => FacilityTypeBlueprint::RESEARCH, 'name' => 'Endcliffe Hatchery'],
                ['type' => FacilityTypeBlueprint::CORPORATE, 'name' => 'Equity House'],
                ['type' => FacilityTypeBlueprint::SECURITY, 'name' => 'Hallamshire Gatehouse'],
            ],
            'protection_cards' => [
                'PS009' => 4, // Orc
                'PS003' => 4, // Security team
                'PS005' => 4, // Keypad
                'PS006' => 4, // Security shutter
                'PS019' => 3, // Roboscorpion
                'PS013' => 3, // Angel
            ],
            'income' => 10,
            'political_will' => 10,
            'credits' => 10,
        ],
        [
            'name' => 'Gordon',
            'facilities' => [
                ['type' => FacilityTypeBlueprint::RESEARCH, 'name' => 'Owlerton Laboratories'],
                ['type' => FacilityTypeBlueprint::CORPORATE, 'name' => 'Gordon Tower'],
                ['type' => FacilityTypeBlueprint::CORPORATE, 'name' => 'Fitzalan Chambers'],
                ['type' => FacilityTypeBlueprint::CORPORATE, 'name' => 'Norfolk Park Registry'],
                ['type' => FacilityTypeBlueprint::SECURITY, 'name' => 'Burngreave Vault'],
            ],
            'protection_cards' => [
                'PS009' => 4, // Orc
                'PS003' => 4, // Security team
                'PS005' => 4, // Keypad
                'PS006' => 4, // Security shutter
                'PS019' => 3, // Roboscorpion
                'PS013' => 3, // Angel
            ],
            'income' => 13,
            'political_will' => 7,
            'credits' => 22,
        ],
        [
            'name' => 'McCullough Calibrated Mechanical',
            'facilities' => [
                ['type' => FacilityTypeBlueprint::RESEARCH, 'name' => 'Attercliffe Laboratories'],
                ['type' => FacilityTypeBlueprint::RESEARCH, 'name' => 'Darnall Test Range'],
                ['type' => FacilityTypeBlueprint::CORPORATE, 'name' => 'McCullough House'],
                ['type' => FacilityTypeBlueprint::SECURITY, 'name' => 'Tinsley Gatehouse'],
                ['type' => FacilityTypeBlueprint::FACTORY, 'name' => 'Templeborough Works'],
            ],
            'protection_cards' => [
                'PS009' => 4, // Orc
                'PS003' => 4, // Security team
                'PS005' => 4, // Keypad
                'PS006' => 4, // Security shutter
                'PS019' => 3, // Roboscorpion
                'PS013' => 3, // Angel
            ],
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
    | The basic Protection Cards a starting Facility opens with
    |--------------------------------------------------------------------------
    |
    | "Each Corporation will begin with a number of Facilities and some basic
    | Protection Cards" (rulebook 3.3). This says how many of each kind go into
    | each of those Facilities, and App\Actions\CreateDefaultFacilities draws
    | them from what the Corporation actually holds - so ANT's Facilities open
    | with ANT's own cards, and nothing is installed that the Corporation does
    | not own a copy of.
    |
    | One of each kind is what fits. A Corporation holds four copies of its
    | commonest card and opens with four or five Facilities, so a single card
    | cannot cover them all; the installer spreads the load across the cards a
    | Corporation holds, which is also what keeps the one-copy-per-Facility rule
    | of 3.3.4 satisfied. Raising these numbers is safe - a Facility simply gets
    | fewer cards than asked for once the holdings run out.
    |
    | The catalogue itself is no longer here. All eighty-three cards, with their
    | codes, challenges and consequences, are in
    | App\Support\ProtectionCardBlueprint, beside the Equipment and technology
    | lists - too long to read comfortably in a config file, and the same shape
    | as the Facility type sheet already in App\Support.
    |
    */

    'installed_in_each' => [
        ProtectionKind::Physical->value => 1,
        ProtectionKind::Cyber->value => 1,
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
