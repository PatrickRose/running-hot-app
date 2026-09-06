<?php

namespace App\Models;

use Database\Factories\TurnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_id
 * @property int $number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['game_id', 'number'])]
class Turn extends Model
{
    /** @use HasFactory<TurnFactory> */
    use HasFactory;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<Phase, $this> */
    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
    }

    /**
     * This turn's sitting of the Council (rulebook 3.1), which spans the Setup
     * and Action phases and so belongs to the turn rather than to either.
     *
     * @return HasOne<CouncilSession, $this>
     */
    public function councilSession(): HasOne
    {
        return $this->hasOne(CouncilSession::class);
    }

    /**
     * The runs against this turn's Facilities (rulebook 3.4).
     *
     * A run belongs to a turn because that is the scope of everything it
     * touches, and 3.4.5 makes it sharper: a run not finished before the Action
     * phase ends is unsuccessful, so one cannot outlive its turn.
     *
     * @return HasMany<Run, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }
}
