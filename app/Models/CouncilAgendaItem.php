<?php

namespace App\Models;

use App\Enums\AgendaItemSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A card in front of one sitting of the Council, and how the vote on it went.
 *
 * @property int $id
 * @property int $council_session_id
 * @property int $agenda_card_id
 * @property AgendaItemSource $source
 * @property bool $secret
 * @property Carbon|null $secret_declared_at
 * @property int|null $outcome_resolution_id
 * @property bool $tie_broken
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CouncilSession $session
 * @property-read AgendaCard $card
 * @property-read Collection<int, CouncilBallot> $ballots
 */
#[Fillable([
    'council_session_id', 'agenda_card_id', 'source', 'secret', 'secret_declared_at',
    'outcome_resolution_id', 'tie_broken', 'resolved_at', 'resolved_by_id',
])]
class CouncilAgendaItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => AgendaItemSource::class,
            'secret' => 'boolean',
            'tie_broken' => 'boolean',
            'secret_declared_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CouncilSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CouncilSession::class, 'council_session_id');
    }

    /** @return BelongsTo<AgendaCard, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(AgendaCard::class, 'agenda_card_id');
    }

    /** @return BelongsTo<AgendaResolution, $this> */
    public function outcome(): BelongsTo
    {
        return $this->belongsTo(AgendaResolution::class, 'outcome_resolution_id');
    }

    /** @return HasMany<CouncilBallot, $this> */
    public function ballots(): HasMany
    {
        return $this->hasMany(CouncilBallot::class);
    }

    /**
     * The ballots that count: everything the Chair has not handed back.
     *
     * @return HasMany<CouncilBallot, $this>
     */
    public function liveBallots(): HasMany
    {
        return $this->ballots()->whereNull('returned_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
