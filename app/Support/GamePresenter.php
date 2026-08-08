<?php

namespace App\Support;

use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Game;
use App\Models\Phase;

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
            'has_discord_webhook' => filled($game->discord_webhook_url ?: config('services.discord.webhook_url')),
            'durations' => [
                'setup_seconds' => $game->setup_seconds,
                'action_seconds' => $game->action_seconds,
                'team_time_seconds' => $game->team_time_seconds,
            ],
            'phase' => $phase === null ? null : $this->phase($phase),
            'server_time' => now()->toIso8601String(),
        ];
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
                ->with('gang:id,name', 'corporation:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Character $character): array => [
                    'subject_type' => $character->getMorphClass(),
                    'subject_id' => $character->id,
                    'name' => $character->name,
                    'role' => $character->role->value,
                    'role_label' => $character->role->label(),
                    'team' => $character->gang->name ?? $character->corporation?->name,
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
