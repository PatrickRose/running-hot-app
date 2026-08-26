<?php

use App\Enums\CharacterRole;
use App\Enums\ProtectionCardAvailability;
use App\Enums\ProtectionKind;
use App\Enums\RunnerSkill;
use App\Support\FacilityTypeBlueprint;

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
    | Protection Card catalogue
    |--------------------------------------------------------------------------
    |
    | The cards Security players are given a list of at the start of the game
    | (rulebook 3.3.3): what is on sale now, what is rumoured to be in
    | progress, and what only specialised research will unlock. Control edits
    | the catalogue during play, and moves a card's availability as the game
    | conditions the rulebook leaves to their judgement come to pass.
    |
    | 'installed_in_each' names the basic cards every starting Facility opens
    | with, outermost last: the list is installed in order, and installing puts
    | each new card in front of the one before it.
    |
    | PLACEHOLDER. Every card below is invented to give the stacks something to
    | hold and the tests something to exercise. None of it is from the game's
    | own card list - the titles, costs, challenge strengths and consequences
    | all need replacing with the real ones. Emptying this list is safe: a game
    | then opens with a catalogue Control fills in, and undefended Facilities.
    |
    */

    'protection_cards' => [
        [
            'name' => 'Chain Link Fence',
            'kind' => ProtectionKind::Physical,
            'cost' => 2,
            'challenge_skill' => RunnerSkill::Brawn,
            'challenge_strength' => 1,
            'consequence' => 'One Alert.',
            'availability' => ProtectionCardAvailability::Available,
        ],
        [
            'name' => 'Contract Guards',
            'kind' => ProtectionKind::Physical,
            'cost' => 4,
            'challenge_skill' => RunnerSkill::Brawn,
            'challenge_strength' => 2,
            'consequence' => 'One Wound.',
            'charge_cost' => 1,
            'charge_consequence' => 'One Tag.',
            'availability' => ProtectionCardAvailability::Available,
        ],
        [
            'name' => 'Blast Door',
            'kind' => ProtectionKind::Physical,
            'cost' => 6,
            'challenge_skill' => RunnerSkill::Brawn,
            'challenge_strength' => 3,
            'consequence' => 'One Wound and one Alert.',
            'availability' => ProtectionCardAvailability::Available,
        ],
        [
            'name' => 'Packet Filter',
            'kind' => ProtectionKind::Cyber,
            'cost' => 2,
            'challenge_skill' => RunnerSkill::Hack,
            'challenge_strength' => 1,
            'consequence' => 'One Alert.',
            'availability' => ProtectionCardAvailability::Available,
        ],
        [
            'name' => 'Honeypot Subnet',
            'kind' => ProtectionKind::Cyber,
            'cost' => 4,
            'challenge_skill' => RunnerSkill::Hack,
            'challenge_strength' => 2,
            'consequence' => 'One Tag.',
            'charge_cost' => 2,
            'charge_consequence' => 'One Alert.',
            'availability' => ProtectionCardAvailability::Available,
        ],
        [
            'name' => 'Black ICE',
            'kind' => ProtectionKind::Cyber,
            'cost' => 8,
            'challenge_skill' => RunnerSkill::Hack,
            'challenge_strength' => 4,
            'consequence' => 'One Wound.',
            'charge_cost' => 2,
            'charge_consequence' => 'One Wound.',
            'availability' => ProtectionCardAvailability::Rumoured,
        ],
        [
            'name' => 'Neural Deadlock',
            'kind' => ProtectionKind::Cyber,
            'cost' => 10,
            'challenge_skill' => RunnerSkill::Hack,
            'challenge_strength' => 5,
            'consequence' => 'Two Wounds.',
            'availability' => ProtectionCardAvailability::ResearchOnly,
        ],
    ],

    'installed_in_each' => ['Chain Link Fence', 'Packet Filter'],

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
