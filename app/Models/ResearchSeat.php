<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Corporation's place at the research table (rulebook 3.2.1).
 *
 * The order is drawn randomly when the session opens and then fixed. A seat
 * that leaves keeps its number rather than being removed, so the rotation does
 * not shuffle under everybody still playing - and so Control can see who left
 * and why.
 *
 * A seat is a Corporation rather than a Research player, because the deck and
 * the Research Points are the Corporation's. A Corporation fielding two
 * Research players plays one hand between them, which is what happens at the
 * table.
 *
 * @property int $id
 * @property int $research_session_id
 * @property int $corporation_id
 * @property int $order
 * @property Carbon|null $left_at
 * @property string|null $left_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Corporation $corporation
 */
#[Fillable(['research_session_id', 'corporation_id', 'order', 'left_at', 'left_reason'])]
class ResearchSeat extends Model
{
    /**
     * The reason recorded when a Corporation's deck runs dry, which is the one
     * way out of the game the rulebook makes automatic.
     */
    public const REASON_DECK_EMPTY = 'Deck ran out';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'left_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ResearchSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ResearchSession::class, 'research_session_id');
    }

    /** @return BelongsTo<Corporation, $this> */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    public function isPlaying(): bool
    {
        return $this->left_at === null;
    }
}
