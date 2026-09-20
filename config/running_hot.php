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
    | Notoriety is carried per Runner and a gang's figure is the total of its
    | members' (rulebook 2.3.2, as the designer reads it), so there is nothing
    | to set on the gang here. Every Runner starts unknown at nought, which is
    | the column default, so none of them names it either.
    |
    | Runner skills are ordered Brawn, Hack, Charisma, Body. The rulebook calls
    | the first skill both "Brute" and "Brawn"; this application standardises on
    | Brawn (see App\Enums\Tracker).
    |
    */

    /**
     * Equipment a Freelancer starts with, keyed by character name.
     *
     * Freelancers belong to no gang - 3.4 hands the Facility game to a side
     * rather than to a roster - so they are named directly rather than through
     * a faction, and they have a briefing of their own.
     *
     * Empty is the right answer for the three the game ships with, not an
     * unfinished one. Jack Scanton, Mandel Reso and Yale Pirit are each given
     * "Special rules" in place of a kit - all three may Direct Security from a
     * Facility and spend their own money doing it - and none of those is an
     * Equipment card. They are Control's to run, exactly as a Facility type's
     * effect is.
     */
    'freelancer_equipment' => [],

    'gangs' => [
        [
            // Equipment (rulebook 3.4.1) is given **per player**: the briefings
            // are one document per Runner, so a Runner's kit goes in an
            // 'equipment' list beside their own stats below, keyed by the code
            // printed on the card. A Runner the config says nothing about opens
            // with nothing, which is the same choice CreateDefaultFacilities
            // makes about Facilities.
            //
            // A briefing heads that list either "Equipment" or "Ability", and
            // both are cards. Ballet, Bitter and Z3R0 are the three given an
            // Ability, and what is printed under it is the effect text of a
            // card in the catalogue - EEP014 to EEP016, the three
            // Reconnaissance cards - reproduced almost word for word rather
            // than described. So they are seeded as the cards they are, and a
            // Runner with an innate power the catalogue does not print would be
            // the thing that needed somewhere else to live.
            //
            // Z3R0's is the one that is not word for word: their Facility
            // Protection reconnaissance is once a turn, views two cards, and
            // warns the Facility's owner on a failure, where EEP014 looks at
            // one card per success and says nothing about being noticed. The
            // card is seeded and Control reads the briefing, which is what
            // Control does with every other printed effect in the game.
            'name' => 'Facers',
            'runners' => [
                [
                    'name' => 'Con', 'brawn' => 3, 'hack' => 3, 'charisma' => 5, 'body' => 4,
                    'equipment' => ['ESP003' => 1, 'ESP004' => 1, 'EEP017' => 1],
                ],
                [
                    'name' => 'Ghost', 'brawn' => 2, 'hack' => 1, 'charisma' => 1, 'body' => 7,
                    'equipment' => ['ESP001' => 1, 'ESP004' => 1],
                ],
                [
                    'name' => 'Next', 'brawn' => 1, 'hack' => 2, 'charisma' => 3, 'body' => 7,
                    'equipment' => ['EES004' => 2],
                ],
                [
                    'name' => 'Vampire', 'brawn' => 1, 'hack' => 1, 'charisma' => 6, 'body' => 7,
                    'equipment' => ['EEP002' => 1],
                ],
                [
                    'name' => 'Wicker', 'brawn' => 2, 'hack' => 2, 'charisma' => 3, 'body' => 6,
                    'equipment' => ['ESP003' => 1, 'ESP004' => 1],
                ],
            ],
        ],
        [
            'name' => 'g33ks',
            'runners' => [
                [
                    'name' => '$0FTW4R3', 'brawn' => 2, 'hack' => 2, 'charisma' => 3, 'body' => 5,
                    'equipment' => ['EEP002' => 1, 'EES004' => 1],
                ],
                [
                    'name' => '$TUX', 'brawn' => 3, 'hack' => 0, 'charisma' => 2, 'body' => 6,
                    'equipment' => ['ESS009' => 4],
                ],
                [
                    'name' => 'CYCL3', 'brawn' => 1, 'hack' => 3, 'charisma' => 3, 'body' => 5,
                    'equipment' => ['EEP021' => 1],
                ],
                [
                    'name' => 'G1T', 'brawn' => 1, 'hack' => 2, 'charisma' => 3, 'body' => 5,
                    'equipment' => ['EEP021' => 1],
                ],
                [
                    'name' => 'Z3R0', 'brawn' => 1, 'hack' => 3, 'charisma' => 3, 'body' => 5,
                    'equipment' => ['EEP014' => 1],
                ],
            ],
        ],
        [
            'name' => 'Dancers',
            'runners' => [
                // Four, not six. The cast list is the roster of record and the
                // Dancers are cast four deep; Rapper and Tango were in here and
                // are in nobody's chair, which would have seated two Runners
                // nobody is playing and given a gang two extra bodies in every
                // dice pool. Their briefings do not exist either.
                [
                    'name' => 'Ballet', 'brawn' => 1, 'hack' => 1, 'charisma' => 6, 'body' => 3,
                    'equipment' => ['EEP015' => 1, 'EEP016' => 1],
                ],
                [
                    'name' => 'Hustle', 'brawn' => 2, 'hack' => 3, 'charisma' => 3, 'body' => 5,
                    'equipment' => ['ESP003' => 1, 'ESP004' => 1, 'EEP017' => 1],
                ],
                [
                    'name' => 'Swing', 'brawn' => 3, 'hack' => 2, 'charisma' => 3, 'body' => 5,
                    'equipment' => ['EES004' => 2, 'ESS006' => 1],
                ],
                [
                    'name' => 'Tap', 'brawn' => 2, 'hack' => 2, 'charisma' => 1, 'body' => 7,
                    'equipment' => ['ESP001' => 1, 'ESP003' => 1],
                ],
            ],
        ],
        [
            'name' => 'Gruffsters',
            'runners' => [
                [
                    'name' => 'Bitter', 'brawn' => 3, 'hack' => 1, 'charisma' => 3, 'body' => 5,
                    'equipment' => ['EEP014' => 1],
                ],
                [
                    'name' => 'Groucho', 'brawn' => 1, 'hack' => 3, 'charisma' => 3, 'body' => 5,
                    'equipment' => ['ERP027' => 1],
                ],
                [
                    'name' => 'Pale', 'brawn' => 3, 'hack' => 1, 'charisma' => 6, 'body' => 5,
                    'equipment' => ['ESP003' => 1, 'ESS006' => 1],
                ],
                [
                    'name' => 'Scorer', 'brawn' => 3, 'hack' => 3, 'charisma' => 3, 'body' => 3,
                    'equipment' => ['EST005' => 3],
                ],
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
    | 'council_votes' seats a character at the Council in their own right, with
    | that many votes. Rulebook 3.1 seats only the Corporations, so this is
    | Control's ruling rather than a rule off the page: the Government player
    | attends and votes with a bloc of six. It is not Political Will and not a
    | tracker - nothing in the game spends it - so Control edits the number
    | directly. Anybody given a number here gets a seat; everybody else has
    | none.
    |
    */

    'unaffiliated' => [
        ['name' => 'Jack Scanton', 'role' => CharacterRole::Freelancer, 'brawn' => 4, 'hack' => 4, 'charisma' => 3, 'body' => 5],
        ['name' => 'Mandel Reso', 'role' => CharacterRole::Freelancer, 'brawn' => 3, 'hack' => 3, 'charisma' => 3, 'body' => 6],
        ['name' => 'Yale Pirit', 'role' => CharacterRole::Freelancer, 'brawn' => 5, 'hack' => 2, 'charisma' => 3, 'body' => 6],
        ['name' => 'Business Times', 'role' => CharacterRole::Press],
        ['name' => 'Th3 Undergr0und', 'role' => CharacterRole::Press],
        ['name' => 'HM Government', 'role' => CharacterRole::Other, 'council_votes' => 6],
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
    | The research decks
    |--------------------------------------------------------------------------
    |
    | What the research game of rulebook 3.2.1 is played out of: five cards in
    | each Corporation's hand, six face up in the public pool, and the two decks
    | those come from.
    |
    | The hand and pool sizes are the rulebook's. Everything else here is not:
    | "Each Corporation's research deck begins as a fairly basic deck" is all
    | 3.2.3 says about what is in one, and the rulebook never describes the
    | public deck at all. So this is a starting position for Control to set
    | rather than a rule being encoded.
    |
    | Two ways of saying what is in a deck, and they add together. The bulk of
    | one is a shape: 'values' is one card of each value in every suit, 'copies'
    | repeats that, and 'wild' adds that many cards of no suit. 'cards' is for
    | everything the shape cannot say, written one entry at a time - a card
    | printed with a marking, a wild worth something other than the rest of
    | them, or two 3s against one 5. An entry is a value plus what makes it
    | particular:
    |
    |     'cards' => [
    |         // One 8 in each of the four suits, each marked "No single".
    |         ['value' => 8, 'markings' => [['marking' => 'no_single']]],
    |         // Two wild 3s.
    |         ['value' => 3, 'wild' => true, 'copies' => 2],
    |         // One 7 of Leaf, and nothing in the other suits.
    |         ['value' => 7, 'suit' => 'leaf'],
    |         // A card printed with both markings at once.
    |         ['value' => 6, 'suit' => 'leaf', 'markings' => [
    |             ['marking' => 'no_single'],
    |             ['marking' => 'restricted', 'suit' => 'cog'],
    |         ]],
    |     ],
    |
    | A card carries a list of markings rather than one, because the two the
    | game prints are about different halves of the equation: 'no_single' says
    | the card cannot be alone in its own set, and 'restricted' names the suit
    | the *other* side has to be - so it needs a 'suit' of its own and says
    | nothing without one. Both are App\Enums\ResearchCardMarking values.
    |
    | A marking the rules do not have, or a 'restricted' naming no suit, stops
    | the seed rather than being written as a null: a card that quietly lost its
    | "No single" would go on being playable alone for the rest of the game.
    |
    | The defaults keep the private decks to low cards and no wilds, because
    | that is what the tech tree implies a basic deck is - the six "Research
    | deck" rows on the common tree sell 3-5s, then 6-10s, and only then wilds.
    | A Corporation named under 'corporations' gets that deck instead of the
    | default one, which is where a Corporation with a research focus of its own
    | would be given it.
    |
    | Applied by App\Actions\SeedResearchDecks, which is a starting position
    | and not a change: nothing goes through TrackerService, and re-running it
    | leaves an existing deck alone rather than dealing a second one on top.
    |
    */

    'research' => [

        'hand_size' => 5,

        'pool_size' => 6,

        'private_deck' => [
            'values' => [1, 2, 3, 4, 5],
            'copies' => 1,
            'wild' => 0,
            'wild_value' => 3,
            'cards' => [],
        ],

        /*
        | The shared deck, and it is the same in every suit. Almost all of it is
        | marked: five cards demanding each suit of the other side, five that
        | cannot be played alone, and only seven plain cards a suit. The wilds
        | are all No single.
        |
        | 138 cards - 32 in each of the four suits, and ten of no suit.
        |
        | Written entirely as `cards`, so the shape above it contributes
        | nothing: every card here is marked or counted in a way the shape
        | cannot say.
        */
        'public_deck' => [
            'values' => [],
            'copies' => 0,

            'cards' => [
                // One of each value in every suit, and it cannot be alone.
                ['values' => [1, 2, 3, 4, 5], 'markings' => [['marking' => 'no_single']]],

                // The same again for each suit the other side may be held to.
                // A Leaf card demanding Leaf is a real card: it makes both sets
                // the same suit.
                ['values' => [1, 2, 3, 4, 5], 'markings' => [['marking' => 'restricted', 'suit' => 'leaf']]],
                ['values' => [1, 2, 3, 4, 5], 'markings' => [['marking' => 'restricted', 'suit' => 'brain']]],
                ['values' => [1, 2, 3, 4, 5], 'markings' => [['marking' => 'restricted', 'suit' => 'maths']]],
                ['values' => [1, 2, 3, 4, 5], 'markings' => [['marking' => 'restricted', 'suit' => 'cog']]],

                // The seven a suit holds with nothing printed on them.
                ['value' => 1, 'copies' => 3],
                ['value' => 2, 'copies' => 2],
                ['value' => 3, 'copies' => 2],

                // And the wilds: two of every value, all No single.
                ['values' => [1, 2, 3, 4, 5], 'wild' => true, 'copies' => 2, 'markings' => [['marking' => 'no_single']]],
            ],
        ],

        /*
        | A Corporation's deck is two major suits and two minor ones, and the
        | shape of each is the same for all five - so it is written once here
        | and a Corporation says only which two it majors in. Thirty-six cards:
        | fourteen in each major suit, four in each minor.
        |
        | Entries name no suit of their own, because the suit is the assignment.
        | Otherwise they read exactly as a `cards` entry does, so a marking goes
        | on one the same way it goes on any other card.
        */
        'suit_decks' => [

            'major' => [
                ['value' => 1, 'copies' => 3],
                ['value' => 1, 'markings' => [['marking' => 'no_single']]],
                ['value' => 2, 'copies' => 2],
                ['value' => 2, 'markings' => [['marking' => 'no_single']]],
                ['value' => 3, 'copies' => 2],
                ['value' => 3, 'markings' => [['marking' => 'no_single']]],
                ['value' => 4],
                ['value' => 4, 'markings' => [['marking' => 'no_single']]],
                ['value' => 5],
                ['value' => 5, 'markings' => [['marking' => 'no_single']]],
            ],

            'minor' => [
                ['value' => 1],
                ['value' => 1, 'markings' => [['marking' => 'no_single']]],
                ['value' => 2],
                ['value' => 2, 'markings' => [['marking' => 'no_single']]],
            ],

        ],

        /*
        | Which two suits each Corporation majors in. Every suit it does not
        | name is minor, so two majors is the game's own shape rather than
        | something enforced - a Corporation Control invents may major in one or
        | in three, and gets the deck that implies.
        |
        | A Corporation named nowhere here gets 'private_deck' above.
        */
        'corporations' => [
            'Augmented Nucleotech' => ['major' => ['maths', 'cog']],
            'Digital Tactical Control' => ['major' => ['maths', 'brain']],
            'Genetic Equity' => ['major' => ['brain', 'leaf']],
            'Gordon' => ['major' => ['cog', 'brain']],
            'McCullough Calibrated Mechanical' => ['major' => ['cog', 'leaf']],
        ],

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
