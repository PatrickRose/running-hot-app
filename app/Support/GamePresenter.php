<?php

namespace App\Support;

use App\Actions\PublishFacilityList;
use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\ProtectionKind;
use App\Enums\ResearchSuit;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\DiscordMemberSync;
use App\Models\EquipmentCardType;
use App\Models\EquipmentHolding;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\Gang;
use App\Models\Phase;
use App\Models\ProtectionCardHolding;
use App\Models\ProtectionCardType;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Models\Turn;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\FacilityDefenceService;
use App\Services\TechnologyService;
use App\Support\Discord\GuildBlueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shapes game state for the Inertia front end.
 *
 * The clock is sent as an absolute server timestamp plus the seconds remaining,
 * so the browser can count down smoothly without ever becoming the authority on
 * when a phase ends.
 */
class GamePresenter
{
    /**
     * What a Plot Facility's owner would grant it, which is nothing.
     *
     * Null slots mean no limit: the cap on a stack exists to make Security
     * Facilities worth building, and Control is not playing that economy. The
     * other two are nought because both are a Corporation's Facilities working
     * on each other, and a Plot Facility has no Corporation.
     *
     * @var array{physical_slots: int|null, cyber_slots: int|null, technology_capacity: int, card_move_discount: int}
     */
    private const PLOT_FACILITY_TOTALS = [
        'physical_slots' => null,
        'cyber_slots' => null,
        'technology_capacity' => 0,
        'card_move_discount' => 0,
    ];

    private ?TechnologyService $technologies = null;

    /**
     * Resolved once per presenter rather than once per Facility: isUsable()
     * is asked about every holding in every Facility a Corporation owns.
     */
    private function technologies(): TechnologyService
    {
        return $this->technologies ??= app(TechnologyService::class);
    }

    /**
     * The game as anybody signed in may read it.
     *
     * Deliberately carries no `discord_webhook_url`: an incoming webhook is a
     * bearer credential, and anybody holding one can post into #announcements
     * as the announcer, with no token and no authentication. In a game where
     * Control's announcements carry rulings, that is not a string to put on the
     * wire to every player because nothing on their pages happens to render it.
     * Control's copy is controlSummary() below, which is the only caller that
     * adds it - so a new player-facing page gets the safe shape by default
     * rather than by remembering to ask for it.
     *
     * @return array<string, mixed>
     */
    public function summary(Game $game): array
    {
        $phase = $game->currentPhase();

        return [
            'id' => $game->id,
            'name' => $game->name,
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'stability' => $game->stability,
            'civil_unrest' => $game->civil_unrest,
            'auto_advance' => $game->auto_advance,
            'durations' => [
                'setup_seconds' => $game->setup_seconds,
                'action_seconds' => $game->action_seconds,
                'team_time_seconds' => $game->team_time_seconds,
            ],
            'phase' => $phase === null ? null : $this->phase($phase),
            'discord' => $this->discord($game),
            'background_url' => $game->background_url,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * The same game as Control reads it, webhook and all.
     *
     * The one key is the difference, so this is a spread of summary() rather
     * than a second copy of the shape: two payloads describing one game is how
     * they drift. Every caller is behind `can:control` or
     * `can:control-game`, which is what makes adding the credential here safe.
     *
     * @return array<string, mixed>
     */
    public function controlSummary(Game $game): array
    {
        return [
            ...$this->summary($game),
            'discord_webhook_url' => $game->discord_webhook_url,
        ];
    }

    /**
     * The state of the game's Discord server: where it is, whether the
     * application has provisioned it, and what it made.
     *
     * @return array<string, mixed>
     */
    public function discord(Game $game): array
    {
        $api = app(DiscordApi::class);

        return [
            'guild_id' => $game->discord_guild_id,
            'invite_url' => $game->discord_invite_url,
            'bot_configured' => $api->isConfigured(),
            'provision_status' => $game->discord_provision_status->value,
            'provision_status_label' => $game->discord_provision_status->label(),
            'provision_in_progress' => $game->discord_provision_status->isInProgress(),
            'provision_message' => $game->discord_provision_message,
            'provisioned_at' => $game->discord_provisioned_at?->toIso8601String(),
            'resource_counts' => $game->discordResources()
                ->selectRaw('kind, count(*) as total')
                ->groupBy('kind')
                ->pluck('total', 'kind')
                ->all(),
        ];
    }

    /**
     * The servers the bot has been added to, for Control to choose from.
     *
     * Saves copying a snowflake by hand. Failure is reported rather than
     * thrown: a bot token that has been revoked should leave the panel usable,
     * with the manual field still there.
     *
     * @return array<string, mixed>
     */
    public function botGuilds(): array
    {
        $api = app(DiscordApi::class);

        if (! $api->isConfigured()) {
            return ['guilds' => [], 'error' => 'No bot token is configured.'];
        }

        try {
            $guilds = $api->botGuilds();
        } catch (Throwable $exception) {
            return ['guilds' => [], 'error' => $exception->getMessage()];
        }

        return [
            'guilds' => array_map(fn (array $guild): array => [
                'id' => (string) $guild['id'],
                'name' => (string) ($guild['name'] ?? $guild['id']),
            ], $guilds),
            'error' => null,
        ];
    }

    /**
     * Who the application has and has not managed to give roles to.
     *
     * The rows that matter to Control are the players who have signed in but
     * never joined the Discord server: without a nudge they sit in the game
     * unable to see any of their team's channels.
     *
     * @return array<int, array<string, mixed>>
     */
    public function discordMemberSyncs(Game $game): array
    {
        return $game->discordMemberSyncs()
            ->with('user:id,name,discord_username')
            ->get()
            ->sortBy(fn (DiscordMemberSync $sync): string => $sync->user->name)
            ->values()
            ->map(fn (DiscordMemberSync $sync): array => [
                'id' => $sync->id,
                'user' => $sync->user->name,
                'discord_username' => $sync->user->discord_username,
                'status' => $sync->status->value,
                'status_label' => $sync->status->label(),
                'message' => $sync->message,
                'role_count' => count($sync->role_ids ?? []),
                'synced_at' => $sync->synced_at?->toIso8601String(),
            ])->all();
    }

    /**
     * The game's Control team: who is running it, rather than playing in it.
     *
     * A seat reads as its account once claimed and as the handle it is waiting
     * on before that, which is the same thing a character's row says.
     *
     * @return array<int, array<string, mixed>>
     */
    public function controlMembers(Game $game): array
    {
        return $game->controlMembers()
            ->with('user:id,name,discord_username')
            ->orderBy('id')
            ->get()
            ->map(fn (ControlMember $member): array => [
                'id' => $member->id,
                'discord_username' => $member->user->discord_username ?? $member->discord_username,
                'claimed_by' => $member->user?->name,
                // So Control can see which seat is theirs before removing one.
                'is_you' => $member->user_id !== null && $member->user_id === auth()->id(),
            ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function phase(Phase $phase): array
    {
        return [
            'id' => $phase->id,
            'turn' => $phase->turn->number,
            'type' => $phase->type->value,
            'type_label' => $phase->type->label(),
            'status' => $phase->status->value,
            'status_label' => $phase->status->label(),
            'starts_at' => $phase->starts_at?->toIso8601String(),
            'ends_at' => $phase->ends_at?->toIso8601String(),
            'remaining_seconds' => $phase->remainingSeconds(),
            'upkeep_applied' => $phase->upkeep_applied_at !== null,
        ];
    }

    /**
     * What the signed-in player is standing at, for the header on every page.
     *
     * The companion to phase(): the clock is on every page because the whole
     * game runs to it, and these numbers are there for the same reason - every
     * decision the game asks of a player is "can I afford this?", and it was
     * only answerable by going back to the dashboard.
     *
     * Two halves, and they are shown to different people. Procatorion's
     * Stability and Civil Unrest belong to the game, so everybody sees them,
     * Control and the two Press outlets included. The characters are the
     * player's own, one entry each because a player may hold more than one and
     * Control holds none; which numbers an entry carries is the role's
     * question, not this method's:
     *
     * - a Corporate player is shown their **Corporation's** Credits, since
     *   that is the purse they spend from and they have no other;
     * - a Runner or Freelancer is shown their own Credits, Wounds and Tags;
     * - a Press outlet and HM Government are shown neither, and are left out
     *   entirely rather than sent as a row of zeroes.
     *
     * @return array<string, mixed>
     */
    public function standing(Game $game, ?User $user): array
    {
        return [
            'stability' => $game->stability,
            'civil_unrest' => $game->civil_unrest,
            'characters' => $user === null ? [] : $this->standingCharacters($game, $user),
        ];
    }

    /**
     * The claimed characters the header draws, and the numbers each one shows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function standingCharacters(Game $game, User $user): array
    {
        return Character::query()
            ->where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->with('corporation:id,name,credits')
            ->orderBy('name')
            ->get()
            ->map(function (Character $character): ?array {
                if ($character->role->isCorporate()) {
                    $corporation = $character->corporation;

                    // A Corporate seat with no Corporation has no purse to
                    // show. Control can build a roster like that, so it is a
                    // shape to cope with rather than an error.
                    return $corporation === null ? null : [
                        'character_id' => $character->id,
                        'character' => $character->name,
                        'subject' => $corporation->name,
                        'credits' => $corporation->credits,
                        'wounds' => null,
                        'tags' => null,
                        'body' => null,
                        'incapacitated' => false,
                    ];
                }

                if (! $character->role->carriesOwnTrackers()) {
                    return null;
                }

                return [
                    'character_id' => $character->id,
                    'character' => $character->name,
                    'subject' => $character->name,
                    'credits' => $character->credits,
                    'wounds' => $character->wounds,
                    'tags' => $character->tags,
                    'body' => $character->body,
                    'incapacitated' => $character->isIncapacitated(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Every tracker Control can move, grouped by subject.
     *
     * @return array<string, mixed>
     */
    public function trackers(Game $game): array
    {
        return [
            'global' => [
                'subject_type' => $game->getMorphClass(),
                'subject_id' => $game->id,
                'values' => [
                    Tracker::Stability->value => $game->stability,
                    Tracker::CivilUnrest->value => $game->civil_unrest,
                ],
            ],
            'corporations' => $game->corporations()->orderBy('name')->get()->map(fn ($corporation): array => [
                'subject_type' => $corporation->getMorphClass(),
                'subject_id' => $corporation->id,
                ...FactionBadge::for($corporation->name),
                'values' => [
                    Tracker::Income->value => $corporation->income,
                    Tracker::PoliticalWill->value => $corporation->political_will,
                    Tracker::CorporationCredits->value => $corporation->credits,
                    // The four Research Point suits sit here rather than on the
                    // research panel, because they are Trackers like the rest:
                    // Control moves them from the same dialog, and every change
                    // lands in the same ledger (rulebook 3.2.1).
                    Tracker::ResearchCog->value => $corporation->cog_points,
                    Tracker::ResearchBrain->value => $corporation->brain_points,
                    Tracker::ResearchLeaf->value => $corporation->leaf_points,
                    Tracker::ResearchMaths->value => $corporation->maths_points,
                ],
            ])->all(),
            // A gang carries no tracker of its own any more: Notoriety is the
            // Runner's, and the gang's figure is the total of its members'. So
            // this is a read-out rather than something Control edits here, and
            // it names no subject - there is nothing to post an adjustment at.
            'gangs' => $game->gangs()
                ->withSum('characters', 'notoriety')
                ->withCount('characters')
                ->orderBy('name')
                ->get()
                ->map(fn (Gang $gang): array => [
                    'id' => $gang->id,
                    ...FactionBadge::for($gang->name),
                    'notoriety' => $gang->notoriety,
                    'members' => $gang->characters_count,
                ])->all(),
            'characters' => $game->characters()
                ->with('gang:id,name', 'corporation:id,name', 'user:id,name,discord_username')
                ->orderBy('name')
                ->get()
                ->map(fn (Character $character): array => [
                    'subject_type' => $character->getMorphClass(),
                    'subject_id' => $character->id,
                    'name' => $character->name,
                    // Only the characters that are organisations rather than
                    // people have artwork - Business Times, Th3 Undergr0und, HM
                    // Government - so this is null for everybody else, and the
                    // page draws nothing rather than falling back to initials.
                    // A coloured square against forty-odd names would imply an
                    // organisation where there is only somebody's name.
                    'logo_path' => LogoImage::pathFor($character->name),
                    'role' => $character->role->value,
                    'role_label' => $character->role->label(),
                    // A Corporate seat has no personal numbers worth moving:
                    // they spend their Corporation's Credits, they never walk
                    // into a Facility to take a Wound or a Tag, and the roster
                    // gives them no runner skills. The Stats screen folds them
                    // away on the strength of this rather than naming the three
                    // roles again in TypeScript.
                    'is_corporate' => $character->role->isCorporate(),
                    'team' => $character->gang->name ?? $character->corporation?->name,
                    'discord_username' => $character->discord_username,
                    // The second claim ticket, for when the handle was never
                    // right. Control's alone: it is on no player-facing payload.
                    'email' => $character->email,
                    'claimed_by' => $character->user?->name,
                    // The four printed stats. Not Trackers: they are what a
                    // character is rather than a number that moves, so Control
                    // edits them outright and no ledger row is written.
                    'brawn' => $character->brawn,
                    'hack' => $character->hack,
                    'charisma' => $character->charisma,
                    'body' => $character->body,
                    'incapacitated' => $character->isIncapacitated(),
                    'values' => [
                        Tracker::Wounds->value => $character->wounds,
                        Tracker::Tags->value => $character->tags,
                        Tracker::CharacterCredits->value => $character->credits,
                        // Theirs, and what their gang's total is made of.
                        Tracker::Notoriety->value => $character->notoriety,
                    ],
                ])->all(),
        ];
    }

    /**
     * The game's Facility type catalogue (rulebook 3.3.1).
     *
     * in_use tells Control whether a type can still be deleted, which saves
     * them finding out by being refused.
     *
     * @return array<int, array<string, mixed>>
     */
    public function facilityTypes(Game $game): array
    {
        return $game->facilityTypes()
            ->withCount('facilities')
            ->orderBy('name')
            ->get()
            ->map(fn (FacilityType $type): array => [
                'id' => $type->id,
                'key' => $type->key,
                'name' => $type->name,
                'description' => $type->description,
                'access_effect' => $type->access_effect,
                'build_cost' => $type->build_cost,
                'physical_slots_granted' => $type->physical_slots_granted,
                'cyber_slots_granted' => $type->cyber_slots_granted,
                'technology_capacity_granted' => $type->technology_capacity_granted,
                'card_move_discount' => $type->card_move_discount,
                'grant_scaling' => $type->grant_scaling->value,
                'grant_scaling_label' => $type->grant_scaling->label(),
                'facility_count' => (int) $type->getAttribute('facilities_count'),
                'in_use' => (int) $type->getAttribute('facilities_count') > 0,
            ])->all();
    }

    /**
     * The Facility list as one player may see it.
     *
     * Two tiers, and the line between them is the whole point. Every player
     * sees the same public list the #facility-list embed carries: a Corporation,
     * a Facility name, a type, and whether it is still building. A player who
     * holds a Corporate role additionally sees their own Corporation's stacks in
     * full, because those are their own defences - rulebook 3.4.2 makes the
     * number of Protection Cards in a Facility Secret from everyone else, not
     * from the Corporation that installed them.
     *
     * A Runner therefore learns nothing here that reconnaissance would
     * otherwise have to buy, and a Security player cannot read a rival's stack.
     *
     * @return array<string, mixed>
     */
    public function facilityBoard(Game $game, ?User $user): array
    {
        $defence = app(FacilityDefenceService::class);
        $turn = $game->currentTurn();
        $turnNumber = $turn?->number;

        $corporations = $game->corporations()
            ->with(['facilities' => fn ($query) => $query->orderBy('name'), 'facilities.facilityType'])
            ->orderBy('name')
            ->get();

        // Worked out together, because both answers need the same user and the
        // second one needs the first: whether they may defend is a question
        // about the Corporation they turn out to sit in.
        $own = null;
        $mayDefend = false;

        if ($user !== null) {
            $own = $this->ownCorporation($game, $user);
            $mayDefend = $own !== null && $this->mayDefend($game, $user, $own);
        }

        return [
            'turn' => $turnNumber,
            'public' => $corporations->map(fn (Corporation $corporation): array => [
                ...FactionBadge::for($corporation->name),
                'is_yours' => $own !== null && $own->is($corporation),
                'facilities' => $corporation->facilities
                    ->sortBy(fn (Facility $facility): string => $facility->facilityType->name.' '.$facility->name)
                    ->values()
                    ->map(fn (Facility $facility): array => [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'facility_type' => $facility->facilityType->name,
                        'available' => $facility->isAvailableOnTurn($turnNumber),
                        'available_from_turn' => $facility->available_from_turn,
                    ])->all(),
            ])->all(),
            // The Facilities nobody owns, listed for everybody exactly as the
            // Corporations' are: a Runner cannot choose a target they have not
            // been told about, and Control builds these to be run against.
            // Never a second tier - no player is inside one of these, so no
            // player reads its stacks.
            //
            // Null rather than an empty group when there are none, so the page
            // draws no heading for a kind of Facility this game does not have.
            // It carries a badge like every other owner, from the same hash, so
            // the browser never has to invent a colour.
            'plot' => $this->plotGroup($game, $turnNumber),
            'own' => $own === null
                ? null
                : $this->ownDefences($own, $mayDefend, $turn, $turnNumber, $defence),
        ];
    }

    /**
     * The Facilities belonging to nobody, as one more owner on the public list.
     *
     * Drawn under {@see Facility::INDEPENDENT_OWNER} rather than as Plot
     * Facilities: this is the list a group chooses a target from, and which
     * buildings Control put there for the story is Control's own hand.
     *
     * @return array<string, mixed>|null
     */
    private function plotGroup(Game $game, ?int $turnNumber): ?array
    {
        $facilities = $game->facilities()
            ->plot()
            ->with('facilityType')
            ->orderBy('name')
            ->get();

        if ($facilities->isEmpty()) {
            return null;
        }

        return [
            ...FactionBadge::for(Facility::INDEPENDENT_OWNER),
            'facilities' => $facilities
                ->map(fn (Facility $facility): array => [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'facility_type' => $facility->facilityType->name,
                    'available' => $facility->isAvailableOnTurn($turnNumber),
                    'available_from_turn' => $facility->available_from_turn,
                ])->all(),
        ];
    }

    /**
     * The Corporation this player sits in, if any.
     *
     * Read from their claimed characters rather than from a column, because a
     * player is bound to a Corporation by holding one of its seats.
     */
    private function ownCorporation(Game $game, User $user): ?Corporation
    {
        $character = $game->characters()
            ->where('user_id', $user->id)
            ->whereNotNull('corporation_id')
            ->with('corporation')
            ->get()
            ->first(fn (Character $character): bool => $character->role->isCorporate());

        return $character?->corporation;
    }

    /**
     * Whether this player may arrange this Corporation's stacks, or only read
     * them.
     *
     * The seat decides. Every Corporate seat sees the stacks, because 3.4.2
     * keeps them Secret from everyone outside the Corporation rather than from
     * the Corporation itself - but the rulebook gives the Facilities to
     * Security, and a board three people can drag at once is a board nobody can
     * trust. Control is not asked about here: Control has its own routes and
     * reaches these Facilities through them.
     */
    private function mayDefend(Game $game, User $user, Corporation $corporation): bool
    {
        if ($game->status !== GameStatus::Running) {
            return false;
        }

        return $game->characters()
            ->where('user_id', $user->id)
            ->where('corporation_id', $corporation->id)
            ->where('role', CharacterRole::Security)
            ->exists();
    }

    /**
     * The copies this Corporation is holding but has not installed.
     *
     * This is the hand Security drags from, so it carries everything a card
     * needs to draw itself rather than an id and a name: the board shows the
     * real card, and dragging a picture of a Roboscorpion into a Facility is
     * the whole point of the screen.
     *
     * A card with no copies left is left out rather than shown greyed. The hand
     * is what you are holding; what you could hold if Control gave you more is
     * the shop's question, and the shop is not built.
     *
     * @return array<int, array<string, mixed>>
     */
    private function handFor(Corporation $corporation): array
    {
        return $corporation->protectionCardHoldings()
            ->where('copies', '>', 0)
            ->with('cardType')
            ->get()
            ->sortBy(fn (ProtectionCardHolding $holding): string => $holding->cardType->kind->value.' '.$holding->cardType->name)
            ->values()
            ->map(fn (ProtectionCardHolding $holding): array => [
                'card_type_id' => $holding->protection_card_type_id,
                'code' => $holding->cardType->code,
                'image_path' => $holding->cardType->imagePath(),
                'name' => $holding->cardType->name,
                'kind' => $holding->cardType->kind->value,
                'kind_label' => $holding->cardType->kind->label(),
                'kind_glyph' => $holding->cardType->kind->glyph(),
                'challenge' => $holding->cardType->challenge,
                'consequence' => $holding->cardType->consequence,
                'charge_cost' => $holding->cardType->charge_cost,
                'charge_consequence' => $holding->cardType->charge_consequence,
                'copies_in_hand' => $holding->copies,
            ])->all();
    }

    /**
     * One Corporation's own defences, in full.
     *
     * @return array<string, mixed>
     */
    private function ownDefences(
        Corporation $corporation,
        bool $mayDefend,
        ?Turn $turn,
        ?int $turnNumber,
        FacilityDefenceService $defence,
    ): array {
        $totals = $defence->derivedTotals($corporation);

        $facilities = $corporation->facilities()
            ->with([
                'facilityType',
                'protectionCards.cardType',
                'technologyHoldings.technologyType.requiredFacilityType',
                'turnStates' => fn ($query) => $query->where('turn_id', $turn?->id),
            ])
            ->orderBy('name')
            ->get();

        return [
            ...FactionBadge::for($corporation->name),
            'credits' => $corporation->credits,
            // Whether this player may drag, or only read.
            'can_defend' => $mayDefend,
            // Only Security needs the hand, and only Security may act on it.
            'hand' => $mayDefend ? $this->handFor($corporation) : [],
            'physical_slots' => $totals['physical_slots'],
            'cyber_slots' => $totals['cyber_slots'],
            'technology_capacity_per_facility' => $totals['technology_capacity'],
            'card_move_discount' => $totals['card_move_discount'],
            'facilities' => $facilities
                ->map(fn (Facility $facility): array => $this->facility($facility, $turnNumber, $totals))
                ->all(),
        ];
    }

    /**
     * Whether the Facility list can be published, and whether it already has.
     *
     * @return array<string, mixed>
     */
    public function facilityList(Game $game): array
    {
        $channel = $game->discordResources()
            ->where('key', GuildBlueprint::CHANNEL_FACILITY_LIST)
            ->first();

        return [
            'channel_exists' => $channel !== null,
            'bot_configured' => app(DiscordApi::class)->isConfigured(),
            'published' => app(PublishFacilityList::class)->hasBeenPublished($game),
        ];
    }

    /**
     * What each Corporation holds of the Protection Card catalogue.
     *
     * Only the cards a Corporation has a stake in: a row in its holdings, or a
     * copy installed somewhere. The full catalogue is eighty-three cards against
     * five Corporations, and a table of four hundred rows that are nearly all
     * zero would hide the six that matter.
     *
     * Both numbers are shown because they are two halves of one count. The
     * briefing gives a Corporation four copies of a card; installing moves one
     * into a Facility, so the hand reads three and the total is still four.
     *
     * @return array<int, array<string, mixed>>
     */
    public function protectionCardHoldings(Game $game): array
    {
        $installed = FacilityProtectionCard::query()
            ->join('facilities', 'facilities.id', '=', 'facility_protection_cards.facility_id')
            ->where('facilities.game_id', $game->id)
            ->groupBy('facilities.corporation_id', 'facility_protection_cards.protection_card_type_id')
            ->select([
                'facilities.corporation_id',
                'facility_protection_cards.protection_card_type_id as card_type_id',
                DB::raw('count(*) as installed'),
            ])
            ->get()
            ->groupBy('corporation_id');

        $catalogue = $game->protectionCardTypes()->get()->keyBy('id');

        return $game->corporations()
            ->with(['protectionCardHoldings.cardType'])
            ->orderBy('name')
            ->get()
            ->map(function (Corporation $corporation) use ($installed, $catalogue): array {
                $installedHere = ($installed->get($corporation->id) ?? collect())
                    ->pluck('installed', 'card_type_id');

                $cards = [];

                foreach ($corporation->protectionCardHoldings as $holding) {
                    $cards[$holding->protection_card_type_id] = $this->holdingRow(
                        $holding->cardType,
                        $holding->copies,
                        (int) ($installedHere[$holding->protection_card_type_id] ?? 0),
                    );
                }

                // A card Control installed that the Corporation was never given
                // a copy of still has to show, or the count on screen would not
                // add up to what is on the table.
                foreach ($installedHere as $cardTypeId => $count) {
                    if (isset($cards[$cardTypeId])) {
                        continue;
                    }

                    $cardType = $catalogue->get($cardTypeId);

                    if ($cardType === null) {
                        continue;
                    }

                    $cards[$cardTypeId] = $this->holdingRow($cardType, 0, (int) $count);
                }

                $cards = array_values($cards);
                usort(
                    $cards,
                    fn (array $a, array $b): int => [$a['kind'], $a['name']] <=> [$b['kind'], $b['name']],
                );

                return [
                    'corporation_id' => $corporation->id,
                    ...FactionBadge::for($corporation->name),
                    'cards' => $cards,
                ];
            })->all();
    }

    /**
     * Who is carrying which Equipment, grouped by the team they belong to.
     *
     * Per Character, because that is what the rulebook caps and what it takes
     * away: three equipped permanent items are *yours* (3.4.1), and *your*
     * permanent Equipment goes to the Security player when you are carried out
     * (3.4.2). A gang's kit is four or five separate hands.
     *
     * Only the cards somebody has a row for, the way the Protection Card
     * holdings work and for the same reason - seventy-four cards against forty
     * characters would be three thousand rows that are almost all zero. A count
     * that has been spent to nothing stays, because a Runner who has used their
     * last Mini-hospital held one and a list that drops it reads as though they
     * never did.
     *
     * Everybody in the game is here, not only the side that runs. 2.1 has
     * Runners buying equipment "from other players", so a card reaches the
     * Facility by way of whoever was holding it - which may well be a CEO who
     * bought it to hand over. Who may hold what is Control's call, and a hand
     * that is empty all game costs a line on a page.
     *
     * The three groups read in the order they matter on this page: the gangs,
     * whose game this mostly is, then the Corporations, then everybody who
     * belongs to neither - the Freelancers, the press and the Government.
     *
     * A viewer narrows it to their own hands. Passing nobody is Control's
     * whole-game view, which is what the Control panel asks for; passing a
     * player gives them the seats they have claimed and nothing else, because a
     * hand is private the way a Facility's stack is. Control passed as the
     * viewer still sees everybody, so the one page can serve both without a
     * second implementation of the shape.
     *
     * @return array<int, array<string, mixed>>
     */
    public function equipmentHoldings(Game $game, ?User $viewer = null): array
    {
        $query = $game->characters()
            ->with(['game', 'gang', 'corporation', 'equipmentHoldings.cardType'])
            ->orderBy('name');

        if ($viewer !== null && ! $viewer->isControlFor($game)) {
            $query->where('user_id', $viewer->id);
        }

        $characters = $query->get();

        $groups = [];

        foreach ($characters as $character) {
            // Asked of the keys rather than the relations, because only the
            // columns know that somebody belongs to no team at all.
            [$key, $name, $hasBadge, $rank] = match (true) {
                $character->gang_id !== null => ['gang:'.$character->gang_id, $character->gang->name, true, 0],
                $character->corporation_id !== null => ['corporation:'.$character->corporation_id, $character->corporation->name, true, 1],
                // Named for what they are rather than given a faction badge
                // that would imply a team standing behind them.
                default => ['unaffiliated', 'Unaffiliated', false, 2],
            };

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    ...FactionBadge::for($name),
                    'has_badge' => $hasBadge,
                    'rank' => $rank,
                    'members' => [],
                ];
            }

            $cards = $character->equipmentHoldings
                // A hand holds what it holds. `EquipmentService` leaves a row
                // at nought rather than deleting it - the count is where it
                // ended up, and a deleted row is a count that never existed -
                // but a card somebody has handed over or spent is not in their
                // hand, and drawing it as a face with a x0 on it is the page
                // saying they are carrying something they are not.
                ->filter(fn (EquipmentHolding $holding): bool => $holding->copies > 0)
                ->map(fn (EquipmentHolding $holding): array => [
                    'card_type_id' => $holding->equipment_card_type_id,
                    'code' => $holding->cardType->code,
                    'name' => $holding->cardType->name,
                    'category' => $holding->cardType->category->value,
                    'category_label' => $holding->cardType->category->label(),
                    'category_glyph' => $holding->cardType->category->glyph(),
                    // What the card does, and its artwork: somebody reading
                    // their own hand is reading the cards, not a list of names.
                    'effect' => $holding->cardType->effect,
                    'image_path' => $holding->cardType->imagePath(),
                    'copies' => $holding->copies,
                ])
                ->sortBy(fn (array $card): array => [$card['category'], $card['name']])
                ->values()
                ->all();

            $groups[$key]['members'][] = [
                'character_id' => $character->id,
                'name' => $character->name,
                'role' => $character->role->value,
                'role_label' => $character->role->label(),
                'cards' => $cards,
                // Whether this viewer may hand a card out of this hand (2.1),
                // asked of the Gate rather than worked out here: the seat, the
                // claim and the shop's own clock are all
                // App\Policies\CharacterPolicy's. False with no viewer, which
                // is the Control panel's own call - it gives through its own
                // route rather than this one.
                'can_give' => $viewer !== null
                    && Gate::forUser($viewer)->allows('giveEquipment', $character),
            ];
        }

        uasort(
            $groups,
            fn (array $a, array $b): int => [$a['rank'], $a['name']] <=> [$b['rank'], $b['name']],
        );

        return array_values(array_map(
            function (array $group): array {
                unset($group['rank']);

                return $group;
            },
            $groups,
        ));
    }

    /**
     * Everybody Control may hand a card to, for the picker that does it.
     *
     * The whole roster rather than the side that runs, for the reason
     * equipmentHoldings() lists the whole roster: 2.1 has a Runner buying
     * equipment from another player, so the card may be going to whoever is
     * about to pass it on. The team travels with the name because forty-odd
     * people are easier to find by the team they are in than by a surname
     * nobody has learnt yet.
     *
     * @return array<int, array{character_id: int, name: string, role_label: string, team: string|null}>
     */
    public function equipmentRecipients(Game $game): array
    {
        return $game->characters()
            ->with(['corporation', 'gang'])
            ->orderBy('name')
            ->get()
            ->map(fn (Character $character): array => [
                'character_id' => $character->id,
                'name' => $character->name,
                'role_label' => $character->role->label(),
                'team' => $character->corporation->name ?? $character->gang?->name,
            ])
            ->all();
    }

    /**
     * The Corporations a Protection Card can be given to, which is all of them.
     *
     * `equipmentRecipients` one table along: 3.3.3 puts the card list in the
     * Security players' hands and 3.3.4 makes the copies the *Corporation's*,
     * so the recipient of a Protection Card is a Corporation where the
     * recipient of an Equipment card is a person. The badge travels with the
     * name for the reason it does everywhere else - a Corporation is a faction,
     * and a row of them should read as one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function protectionCardRecipients(Game $game): array
    {
        return $game->corporations()
            ->orderBy('name')
            ->get()
            ->map(fn (Corporation $corporation): array => [
                'corporation_id' => $corporation->id,
                ...FactionBadge::for($corporation->name),
            ])
            ->all();
    }

    /**
     * The tech trees a technology can be put on.
     *
     * The set from the card sheet, plus whichever Corporations this game has -
     * Control may have built a roster of their own, and a technology they write
     * during play belongs to one of those rather than to a name from the sheet.
     *
     * @return array<int, array{tree: string, label: string, corporation_id: int|null}>
     */
    public function technologyTrees(Game $game): array
    {
        $corporations = $game->corporations()->orderBy('name')->get();
        $names = TechnologyBlueprint::corporationNames();

        $trees = [[
            'tree' => TechnologyBlueprint::COMMON,
            'label' => 'Common to every Corporation',
            'corporation_id' => null,
        ]];

        foreach ($corporations as $corporation) {
            $trees[] = [
                // A Corporation the card sheet knows keeps that key, so a
                // technology Control writes files with the seeded ones.
                'tree' => array_search($corporation->name, $names, true)
                    ?: Str::slug($corporation->name),
                'label' => $corporation->name,
                'corporation_id' => $corporation->id,
            ];
        }

        return $trees;
    }

    /**
     * The four Research Point suits, with the icon that draws each.
     *
     * Sent once for the page rather than repeated on all four costs of every
     * technology, which would be five hundred copies of the same four letters.
     *
     * @return array<int, array{value: string, label: string, glyph: string}>
     */
    public function researchSuits(): array
    {
        return array_map(fn (ResearchSuit $suit): array => [
            'value' => $suit->value,
            'label' => $suit->label(),
            'glyph' => $suit->glyph(),
        ], ResearchSuit::all());
    }

    /**
     * The game's Equipment catalogue (rulebook 3.4.1).
     *
     * @return array<int, array<string, mixed>>
     */
    public function equipmentCardTypes(Game $game): array
    {
        return $game->equipmentCardTypes()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (EquipmentCardType $card): array => [
                'id' => $card->id,
                'code' => $card->code,
                'image_path' => $card->imagePath(),
                'name' => $card->name,
                'category' => $card->category->value,
                'category_label' => $card->category->label(),
                'category_glyph' => $card->category->glyph(),
                'effect' => $card->effect,
                'cost' => $card->cost,
                'notes' => $card->notes,
            ])->all();
    }

    /**
     * The game's technologies (rulebook 3.2.2).
     *
     * @return array<int, array<string, mixed>>
     */
    public function technologyTypes(Game $game): array
    {
        return $game->technologyTypes()
            ->with(['corporation', 'requiredFacilityType'])
            ->orderBy('tree')
            ->orderBy('code')
            ->get()
            ->map(fn (TechnologyType $technology): array => [
                'id' => $technology->id,
                'code' => $technology->code,
                'image_path' => $technology->imagePath(),
                'back_image_path' => $technology->backImagePath(),
                'name' => $technology->name,
                'tree' => $technology->tree,
                'corporation' => $technology->corporation?->name,
                'description' => $technology->description,
                'effect' => $technology->effect,
                'cost' => $technology->cost(),
                'is_free' => $technology->isFree(),
                'prerequisites' => $technology->prerequisites,
                'required_facility_type' => $technology->requiredFacilityType?->name,
                'copy_strength' => $technology->copy_strength,
                'destroy_strength' => $technology->destroy_strength,
            ])->all();
    }

    /**
     * One Corporation's stake in one card.
     *
     * @return array<string, mixed>
     */
    private function holdingRow(ProtectionCardType $cardType, int $inHand, int $installed): array
    {
        return [
            'card_type_id' => $cardType->id,
            'code' => $cardType->code,
            'name' => $cardType->name,
            'kind' => $cardType->kind->value,
            'kind_label' => $cardType->kind->label(),
            'kind_glyph' => $cardType->kind->glyph(),
            'copies_in_hand' => $inHand,
            'installed' => $installed,
        ];
    }

    /**
     * The card list every player may read: the Protection Cards and the
     * Equipment, as printed, and nothing Control keeps beside them.
     *
     * A catalogue is not one of the things 3.4.2 keeps Secret - which cards
     * stand in which Facility is, and so is how many - so the rows carry the
     * card and not the game's use of it. `installed_count` is gone because it
     * says how many copies of a card are standing across the game's
     * Facilities, which is reconnaissance by arithmetic, and `notes` because
     * those are Control's own. Technologies are left off altogether: they are
     * the research game's, and a tech tree is a Corporation's to read.
     *
     * @return array{protection: array<int, array<string, mixed>>, equipment: array<int, array<string, mixed>>}
     */
    public function publicCardList(Game $game): array
    {
        return [
            'protection' => array_map(
                fn (array $card): array => Arr::except($card, ['installed_count', 'notes']),
                $this->protectionCardTypes($game),
            ),
            'equipment' => array_map(
                fn (array $card): array => Arr::except($card, ['notes']),
                $this->equipmentCardTypes($game),
            ),
        ];
    }

    /**
     * The game's Protection Card catalogue (rulebook 3.3.2).
     *
     * @return array<int, array<string, mixed>>
     */
    public function protectionCardTypes(Game $game): array
    {
        return $game->protectionCardTypes()
            ->withCount('installations')
            ->orderBy('kind')
            ->orderBy('name')
            ->get()
            ->map(fn (ProtectionCardType $card): array => [
                'id' => $card->id,
                'code' => $card->code,
                'image_path' => $card->imagePath(),
                'name' => $card->name,
                'kind' => $card->kind->value,
                'kind_label' => $card->kind->label(),
                'kind_glyph' => $card->kind->glyph(),
                'cost' => $card->cost,
                'challenge' => $card->challenge,
                'consequence' => $card->consequence,
                'charge_cost' => $card->charge_cost,
                'charge_consequence' => $card->charge_consequence,
                'availability' => $card->availability->value,
                'availability_label' => $card->availability->label(),
                'notes' => $card->notes,
                'installed_count' => (int) $card->getAttribute('installations_count'),
            ])->all();
    }

    /**
     * Every Facility in the game, grouped by Corporation.
     *
     * Slot limits and technology capacity are derived here rather than stored,
     * because both move the moment a Security or Corporate Facility opens.
     *
     * @return array<int, array<string, mixed>>
     */
    public function facilities(Game $game): array
    {
        $defence = app(FacilityDefenceService::class);
        $turn = $game->currentTurn();
        $turnNumber = $turn?->number;

        // Which Discord channels the application has on record, so Control can
        // see at a glance whether a Facility has somewhere to be run against.
        $channelKeys = array_fill_keys($game->discordResources()->pluck('key')->all(), true);

        return $game->corporations()
            ->with([
                'facilities' => fn ($query) => $query->orderBy('name'),
                'facilities.facilityType',
                'facilities.protectionCards.cardType',
                'facilities.technologyHoldings.technologyType.requiredFacilityType',
                'facilities.turnStates' => fn ($query) => $query->where('turn_id', $turn?->id),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($corporation) use ($defence, $turnNumber, $channelKeys): array {
                // Derived once per Corporation: every one of these depends on
                // the whole Facility list, not on the Facility being described.
                $totals = $defence->derivedTotals($corporation);

                return [
                    'id' => $corporation->id,
                    ...FactionBadge::for($corporation->name),
                    'credits' => $corporation->credits,
                    'physical_slots' => $totals['physical_slots'],
                    'cyber_slots' => $totals['cyber_slots'],
                    'technology_capacity_per_facility' => $totals['technology_capacity'],
                    'card_move_discount' => $totals['card_move_discount'],
                    'facilities' => $corporation->facilities
                        ->map(fn (Facility $facility): array => $this->facility($facility, $turnNumber, $totals, $channelKeys))
                        ->all(),
                ];
            })->all();
    }

    /**
     * The Facilities belonging to nobody: the Plot Facilities Control builds
     * for the Runners to hit (see {@see Facility::INDEPENDENT_OWNER}).
     *
     * Their own list rather than a tenth Corporation in facilities(), because
     * almost nothing that describes a Corporation is true of them - there are
     * no Credits behind them, no hand of copies to install from, no Security
     * seat and no slot limit at all. A fake Corporation carrying four zeroes
     * and a null would be a shape the browser had to keep checking.
     *
     * @return array<int, array<string, mixed>>
     */
    public function plotFacilities(Game $game): array
    {
        $turn = $game->currentTurn();
        $turnNumber = $turn?->number;

        $channelKeys = array_fill_keys($game->discordResources()->pluck('key')->all(), true);

        return $game->facilities()
            ->plot()
            ->with([
                'facilityType',
                'protectionCards.cardType',
                'turnStates' => fn ($query) => $query->where('turn_id', $turn?->id),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Facility $facility): array => $this->facility(
                $facility,
                $turnNumber,
                self::PLOT_FACILITY_TOTALS,
                $channelKeys,
            ))
            ->all();
    }

    /**
     * @param  array{physical_slots: int|null, cyber_slots: int|null, technology_capacity: int, card_move_discount: int}  $totals
     *                                                                                                                             a null slot count means no limit, which is
     *                                                                                                                             what a Plot Facility has
     * @param  array<string, bool>|null  $channelKeys  recorded Discord resource keys,
     *                                                 or null for a caller with no business knowing
     * @return array<string, mixed>
     */
    protected function facility(
        Facility $facility,
        ?int $turnNumber,
        array $totals,
        ?array $channelKeys = null,
    ): array {
        $state = $facility->turnStates->first();

        return [
            'id' => $facility->id,
            'name' => $facility->name,
            'corporation_id' => $facility->corporation_id,
            'is_plot' => $facility->isPlotFacility(),
            'facility_type_id' => $facility->facility_type_id,
            'facility_type' => $facility->facilityType->name,
            'available_from_turn' => $facility->available_from_turn,
            'available' => $facility->isAvailableOnTurn($turnNumber),
            'notes' => $facility->notes,
            // What is stored in this Facility, for the Corporation that owns it
            // and for Control.
            //
            // The same tier line the stacks are on: 3.4.2 keeps a Facility's
            // contents Secret from *outside* the Corporation, so that
            // reconnaissance costs something - not from the people who put them
            // there. facility() is only ever built for the own tier and for
            // Control; the public list in facilityBoard() is assembled
            // separately and names none of this.
            'technology_capacity' => $totals['technology_capacity'],
            'technologies' => $facility->technologyHoldings
                // What is actually in the building. A card a Run stole or
                // destroyed keeps its row - the ledger of what a Corporation
                // once had is worth more than a tidy table - but it is not
                // here any more, and listing it made the count on screen
                // disagree with the capacity the server enforces.
                ->filter(fn (TechnologyHolding $holding): bool => $holding->status->occupiesStorage())
                ->sortBy(fn (TechnologyHolding $holding): string => $holding->technologyType->name)
                ->values()
                ->map(fn (TechnologyHolding $holding): array => [
                    'id' => $holding->id,
                    'name' => $holding->technologyType->name,
                    'code' => $holding->technologyType->code,
                    'image_path' => $holding->technologyType->imagePath(),
                    'description' => $holding->technologyType->description,
                    'effect' => $holding->technologyType->effect,
                    'status' => $holding->status->value,
                    'status_label' => $holding->status->label(),
                    'origin_label' => $holding->origin->label(),
                    // So the board can refuse a Facility that cannot house this
                    // card before asking, and say why. The service refuses it
                    // too, and is the one that decides.
                    'required_facility_type_id' => $holding->technologyType->required_facility_type_id,
                    'required_facility_type' => $holding->technologyType->requiredFacilityType?->name,
                    // 3.2.7 in one boolean: a claimed copy is paper until it is
                    // paid for, and a stolen piece of a split technology does
                    // nothing until its thief holds every piece.
                    'usable' => $this->technologies()->isUsable($holding),
                ])->all(),
            'stacks' => array_map(
                fn (ProtectionKind $kind): array => [
                    'kind' => $kind->value,
                    'kind_label' => $kind->label(),
                    'kind_glyph' => $kind->glyph(),
                    'slots' => $kind === ProtectionKind::Physical
                        ? $totals['physical_slots']
                        : $totals['cyber_slots'],
                    'cards' => $facility->protectionCards
                        ->where('kind', $kind)
                        ->sortBy('position')
                        ->values()
                        ->map(fn (FacilityProtectionCard $card): array => [
                            'id' => $card->id,
                            'position' => $card->position,
                            'card_type_id' => $card->protection_card_type_id,
                            'code' => $card->cardType->code,
                            'image_path' => $card->cardType->imagePath(),
                            'name' => $card->cardType->name,
                            'challenge' => $card->cardType->challenge,
                            'consequence' => $card->cardType->consequence,
                            'charge_cost' => $card->cardType->charge_cost,
                            'charge_consequence' => $card->cardType->charge_consequence,
                        ])->all(),
                ],
                ProtectionKind::encounterOrder(),
            ),
            // A Facility nobody has touched this turn has no state row, which
            // reads the same as an untouched one: no meeple, no budget.
            'channels' => $channelKeys === null ? null : [
                'text' => isset($channelKeys[GuildBlueprint::facilityChannelKey($facility, 'text')]),
                'voice' => isset($channelKeys[GuildBlueprint::facilityChannelKey($facility, 'voice')]),
            ],
            'security' => [
                'budget' => $state === null ? 0 : $state->security_budget,
                'budget_spent' => $state === null ? 0 : $state->security_budget_spent,
                'budget_returned' => $state !== null && $state->budget_returned_at !== null,
                'cards_removed' => $state === null ? 0 : $state->cards_removed,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentAdjustments(Game $game, int $limit = 40): array
    {
        return $game->trackerAdjustments()
            ->with('actor:id,name', 'subject')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn ($adjustment): array => [
                'id' => $adjustment->id,
                'tracker' => $adjustment->tracker->value,
                'tracker_label' => $adjustment->tracker->label(),
                'subject' => $adjustment->subject?->getAttribute('name') ?? 'Procatorion',
                'value_before' => $adjustment->value_before,
                'value_after' => $adjustment->value_after,
                'delta' => $adjustment->delta,
                'reason' => $adjustment->reason,
                'automated' => $adjustment->automated,
                'actor' => $adjustment->actor?->name,
                'at' => $adjustment->created_at?->toIso8601String(),
            ])->all();
    }
}
