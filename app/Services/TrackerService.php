<?php

namespace App\Services;

use App\Enums\Tracker;
use App\Models\Game;
use App\Models\TrackerAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads and writes the game's numeric trackers, recording every movement so
 * Control can audit it later.
 */
class TrackerService
{
    /**
     * Move a tracker by a relative amount.
     */
    public function adjust(
        Model $subject,
        Tracker $tracker,
        int $delta,
        ?string $reason = null,
        ?User $actor = null,
        bool $automated = false,
    ): TrackerAdjustment {
        return $this->write($subject, $tracker, fn (int $current): int => $current + $delta, $reason, $actor, $automated);
    }

    /**
     * Set a tracker to an absolute value.
     */
    public function set(
        Model $subject,
        Tracker $tracker,
        int $value,
        ?string $reason = null,
        ?User $actor = null,
        bool $automated = false,
    ): TrackerAdjustment {
        return $this->write($subject, $tracker, fn (): int => $value, $reason, $actor, $automated);
    }

    /**
     * @param  callable(int): int  $resolve
     */
    protected function write(
        Model $subject,
        Tracker $tracker,
        callable $resolve,
        ?string $reason,
        ?User $actor,
        bool $automated,
    ): TrackerAdjustment {
        $expected = $tracker->subjectClass();

        if (! $subject instanceof $expected) {
            throw new InvalidArgumentException(
                sprintf('Tracker [%s] cannot be applied to [%s].', $tracker->value, $subject::class)
            );
        }

        return DB::transaction(function () use ($subject, $tracker, $resolve, $reason, $actor, $automated): TrackerAdjustment {
            /** @var Model $subject */
            $subject = $subject->newQuery()->lockForUpdate()->findOrFail($subject->getKey());

            $column = $tracker->column();
            $before = (int) $subject->getAttribute($column);
            $after = $resolve($before);

            $minimum = $tracker->minimum();

            if ($minimum !== null) {
                $after = max($minimum, $after);
            }

            $subject->setAttribute($column, $after);
            $subject->save();

            $game = $this->gameFor($subject);

            return TrackerAdjustment::create([
                'game_id' => $game->id,
                'phase_id' => $game->currentPhase()?->id,
                'actor_id' => $actor?->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'tracker' => $tracker,
                'value_before' => $before,
                'value_after' => $after,
                'delta' => $after - $before,
                'reason' => $reason,
                'automated' => $automated,
            ]);
        });
    }

    protected function gameFor(Model $subject): Game
    {
        if ($subject instanceof Game) {
            return $subject;
        }

        /** @var Game */
        return Game::query()->findOrFail($subject->getAttribute('game_id'));
    }
}
