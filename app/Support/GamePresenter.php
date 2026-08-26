<?php

namespace App\Support;

use App\Actions\PublishFacilityList;
use App\Enums\ProtectionKind;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\DiscordMemberSync;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\Phase;
use App\Models\ProtectionCardType;
use App\Models\Turn;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\FacilityDefenceService;
use App\Support\Discord\GuildBlueprint;
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
                'name' => $corporation->name,
                'values' => [
                    Tracker::Income->value => $corporation->income,
                    Tracker::PoliticalWill->value => $corporation->political_will,
                    Tracker::CorporationCredits->value => $corporation->credits,
                ],
            ])->all(),
            'gangs' => $game->gangs()->orderBy('name')->get()->map(fn ($gang): array => [
                'subject_type' => $gang->getMorphClass(),
                'subject_id' => $gang->id,
                'name' => $gang->name,
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

        $own = $user === null ? null : $this->ownCorporation($game, $user);

        return [
            'turn' => $turnNumber,
            'public' => $corporations->map(fn (Corporation $corporation): array => [
                'name' => $corporation->name,
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
            'own' => $own === null ? null : $this->ownDefences($own, $turn, $turnNumber, $defence),
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
     * One Corporation's own defences, in full.
     *
     * @return array<string, mixed>
     */
    private function ownDefences(
        Corporation $corporation,
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
            'name' => $corporation->name,
            'credits' => $corporation->credits,
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
                'name' => $card->name,
                'kind' => $card->kind->value,
                'kind_label' => $card->kind->label(),
                'cost' => $card->cost,
                'challenge_skill' => $card->challenge_skill->value,
                'challenge_skill_label' => $card->challenge_skill->label(),
                'challenge_strength' => $card->challenge_strength,
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

        return $game->corporations()
            ->with([
                'facilities' => fn ($query) => $query->orderBy('name'),
                'facilities.facilityType',
                'facilities.protectionCards.cardType',
                'facilities.turnStates' => fn ($query) => $query->where('turn_id', $turn?->id),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($corporation) use ($defence, $turnNumber): array {
                // Derived once per Corporation: every one of these depends on
                // the whole Facility list, not on the Facility being described.
                $totals = $defence->derivedTotals($corporation);

                return [
                    'id' => $corporation->id,
                    'name' => $corporation->name,
                    'credits' => $corporation->credits,
                    'physical_slots' => $totals['physical_slots'],
                    'cyber_slots' => $totals['cyber_slots'],
                    'technology_capacity_per_facility' => $totals['technology_capacity'],
                    'card_move_discount' => $totals['card_move_discount'],
                    'facilities' => $corporation->facilities
                        ->map(fn (Facility $facility): array => $this->facility($facility, $turnNumber, $totals))
                        ->all(),
                ];
            })->all();
    }

    /**
     * @param  array{physical_slots: int, cyber_slots: int, technology_capacity: int, card_move_discount: int}  $totals
     * @return array<string, mixed>
     */
    protected function facility(Facility $facility, ?int $turnNumber, array $totals): array
    {
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
                            'name' => $card->cardType->name,
                            'challenge' => sprintf(
                                '%s %d',
                                $card->cardType->challenge_skill->label(),
                                $card->cardType->challenge_strength,
                            ),
                            'consequence' => $card->cardType->consequence,
                            'charge_cost' => $card->cardType->charge_cost,
                            'charge_consequence' => $card->cardType->charge_consequence,
                        ])->all(),
                ],
                ProtectionKind::encounterOrder(),
            ),
            // A Facility nobody has touched this turn has no state row, which
            // reads the same as an untouched one: no meeple, no budget.
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
