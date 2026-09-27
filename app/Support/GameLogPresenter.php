<?php

namespace App\Support;

use App\Enums\Tracker;
use App\Models\Game;
use App\Models\Phase;
use App\Models\TrackerAdjustment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The whole tracker ledger, for Control to read back.
 *
 * The Stats page carries the last forty movements, which answers "what just
 * happened?" and nothing older - and "why did that number change?" is asked
 * three turns later, which is the whole reason `tracker_adjustments` exists.
 * This is every row, newest first, a page at a time, narrowed server-side by
 * tracker, subject and turn so that finding one movement in a long evening does
 * not mean sending the browser all of them.
 *
 * Read-only. A ruling made after reading it goes through the tracker controls
 * like any other, and lands in this same ledger.
 */
class GameLogPresenter
{
    public const PER_PAGE = 100;

    /**
     * @param  array{tracker?: string|null, subject?: string|null, turn?: int|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function page(Game $game, array $filters): LengthAwarePaginator
    {
        return $this->filtered($game, $filters)
            ->with('actor:id,name', 'subject', 'phase.turn')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (TrackerAdjustment $adjustment): array => self::row($adjustment));
    }

    /**
     * What the filters can be set to: every tracker, and only the subjects and
     * turns the ledger actually has rows for, so no choice finds nothing.
     *
     * @return array{
     *     trackers: list<array{value: string, label: string}>,
     *     subjects: array<int, array{value: string, label: string}>,
     *     turns: array<int, int>,
     * }
     */
    public function options(Game $game): array
    {
        $subjects = $game->trackerAdjustments()
            ->select('subject_type', 'subject_id')
            ->distinct()
            ->with('subject')
            ->get()
            ->map(fn (TrackerAdjustment $adjustment): array => [
                'value' => $adjustment->subject_type.':'.$adjustment->subject_id,
                'label' => self::subjectName($adjustment),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $turns = $game->turns()
            ->whereHas('phases', fn (Builder $phases) => $phases->whereIn(
                'id',
                $game->trackerAdjustments()->select('phase_id'),
            ))
            ->orderByDesc('number')
            ->pluck('number')
            ->map(fn ($number): int => (int) $number)
            ->all();

        return [
            'trackers' => array_map(
                fn (Tracker $tracker): array => ['value' => $tracker->value, 'label' => $tracker->label()],
                Tracker::cases(),
            ),
            'subjects' => $subjects,
            'turns' => $turns,
        ];
    }

    /**
     * One ledger row, as both this page and the Stats page's short log draw it.
     *
     * @return array<string, mixed>
     */
    public static function row(TrackerAdjustment $adjustment): array
    {
        $phase = $adjustment->phase;

        return [
            'id' => $adjustment->id,
            'tracker' => $adjustment->tracker->value,
            'tracker_label' => $adjustment->tracker->label(),
            'subject' => self::subjectName($adjustment),
            'value_before' => $adjustment->value_before,
            'value_after' => $adjustment->value_after,
            'delta' => $adjustment->delta,
            'reason' => $adjustment->reason,
            'automated' => $adjustment->automated,
            'actor' => $adjustment->actor?->name,
            'turn' => $phase?->turn?->number,
            'phase' => $phase?->type->label(),
            'at' => $adjustment->created_at?->toIso8601String(),
        ];
    }

    /**
     * A movement of the game's own trackers names no subject row, and a
     * character since deleted names nothing at all.
     */
    private static function subjectName(TrackerAdjustment $adjustment): string
    {
        if ($adjustment->subject_type === 'game') {
            return 'Procatorion';
        }

        return $adjustment->subject?->getAttribute('name') ?? 'Removed';
    }

    /**
     * @param  array{tracker?: string|null, subject?: string|null, turn?: int|null}  $filters
     * @return Builder<TrackerAdjustment>
     */
    private function filtered(Game $game, array $filters): Builder
    {
        $query = TrackerAdjustment::query()->whereBelongsTo($game);

        $tracker = Tracker::tryFrom((string) ($filters['tracker'] ?? ''));

        if ($tracker !== null) {
            $query->where('tracker', $tracker);
        }

        $subject = (string) ($filters['subject'] ?? '');

        if (preg_match('/^([a-z_]+):(\d+)$/', $subject, $matches) === 1) {
            $query->where('subject_type', $matches[1])->where('subject_id', (int) $matches[2]);
        }

        $turn = $filters['turn'] ?? null;

        if ($turn !== null) {
            $query->whereIn(
                'phase_id',
                Phase::query()
                    ->select('id')
                    ->whereHas('turn', fn (Builder $turns) => $turns->whereBelongsTo($game)->where('number', $turn)),
            );
        }

        return $query;
    }
}
