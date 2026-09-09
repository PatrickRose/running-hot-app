<?php

namespace App\Models;

use App\Enums\RunStatus;
use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One group of Runners going at one Facility (rulebook 3.4).
 *
 * The target is Secret from everyone but the group and Control, which the
 * policy and the presenter enforce rather than the schema: the application has
 * to know the target in order to order the groups queueing at it.
 *
 * @property int $id
 * @property int $game_id
 * @property int $turn_id
 * @property int $facility_id
 * @property int|null $run_leader_character_id
 * @property RunStatus $status
 * @property int|null $order_index
 * @property string|null $order_reason
 * @property int $alerts
 * @property int $alerts_spent
 * @property int $cards_passed
 * @property int $active_cards_passed
 * @property bool $retry_pending
 * @property int $ignored_end_the_run
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read Turn $turn
 * @property-read Facility $facility
 * @property-read Character|null $leader
 * @property-read Collection<int, RunParticipant> $participants
 * @property-read Collection<int, RunEvent> $events
 * @property-read Collection<int, RunDiceRoll> $diceRolls
 */
#[Fillable([
    'game_id',
    'turn_id',
    'facility_id',
    'run_leader_character_id',
    'status',
    'order_index',
    'order_reason',
    'alerts',
    'alerts_spent',
    'cards_passed',
    'active_cards_passed',
    'retry_pending',
    'ignored_end_the_run',
    'started_at',
    'ended_at',
])]
class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'retry_pending' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Turn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'run_leader_character_id');
    }

    /** @return HasMany<RunParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(RunParticipant::class)->orderBy('position');
    }

    /** @return HasMany<RunEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RunEvent::class)->orderBy('id');
    }

    /** @return HasMany<RunDiceRoll, $this> */
    public function diceRolls(): HasMany
    {
        return $this->hasMany(RunDiceRoll::class)->orderBy('id');
    }

    /**
     * The Runners still in, in Breather order.
     *
     * The group shrinks as people walk away or are incapacitated, and almost
     * every question the loop asks - how big is the dice pool, who can take
     * this consequence, is anyone left - is about these rather than about
     * everyone who set out.
     *
     * @return Collection<int, RunParticipant>
     */
    public function activeParticipants(): Collection
    {
        return $this->participants->whereNull('left_at')->values();
    }

    /**
     * Alerts Security still has in hand.
     *
     * The balance rather than the total, because Alerts do two jobs at once:
     * they are temporary Credits and they are strength on every card the
     * Runners have left. Spending them buys something now and makes the rest
     * of the Facility easier, which is the whole tension (3.4.2).
     */
    public function alertsAvailable(): int
    {
        return max(0, $this->alerts - $this->alerts_spent);
    }
}
