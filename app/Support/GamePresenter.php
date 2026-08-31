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
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\Phase;
use App\Models\ProtectionCardHolding;
use App\Models\ProtectionCardType;
use App\Models\TechnologyType;
use App\Models\Turn;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\FacilityDefenceService;
use App\Support\Discord\GuildBlueprint;
use Illuminate\Support\Facades\DB;
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
            'discord_webhook_url' => $game->discord_webhook_url,
            'durations' => [
                'setup_seconds' => $game->setup_seconds,
                'action_seconds' => $game->action_seconds,
                'team_time_seconds' => $game->team_time_seconds,
            ],
            'phase' => $phase === null ? null : $this->phase($phase),
            'discord' => $this->discord($game),
            'server_time' => now()->toIso8601String(),
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
                ],
            ])->all(),
            'gangs' => $game->gangs()->orderBy('name')->get()->map(fn ($gang): array => [
                'subject_type' => $gang->getMorphClass(),
                'subject_id' => $gang->id,
                ...FactionBadge::for($gang->name),
                'values' => [
                    Tracker::Notoriety->value => $gang->notoriety,
                ],
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
                    'team' => $character->gang->name ?? $character->corporation?->name,
                    'discord_username' => $character->discord_username,
                    'claimed_by' => $character->user?->name,
                    'body' => $character->body,
                    'incapacitated' => $character->isIncapacitated(),
                    'values' => [
                        Tracker::Wounds->value => $character->wounds,
                        Tracker::Tags->value => $character->tags,
                        Tracker::CharacterCredits->value => $character->credits,
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
            'own' => $own === null
                ? null
                : $this->ownDefences($own, $mayDefend, $turn, $turnNumber, $defence),
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
            ->with(['facilityType', 'protectionCards.cardType', 'turnStates' => fn ($query) => $query->where('turn_id', $turn?->id)])
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
     * @param  array{physical_slots: int, cyber_slots: int, technology_capacity: int, card_move_discount: int}  $totals
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
            'facility_type_id' => $facility->facility_type_id,
            'facility_type' => $facility->facilityType->name,
            'available_from_turn' => $facility->available_from_turn,
            'available' => $facility->isAvailableOnTurn($turnNumber),
            'notes' => $facility->notes,
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
                'directed' => $state !== null && $state->security_directed,
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
