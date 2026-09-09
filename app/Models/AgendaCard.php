<?php

namespace App\Models;

use App\Enums\AgendaCardStatus;
use Database\Factories\AgendaCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One agenda card: what the Council is being asked, and the resolutions it may
 * choose between (rulebook 3.1).
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $submitted_by_character_id
 * @property string $title
 * @property string|null $body
 * @property string|null $control_note
 * @property AgendaCardStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read Character|null $author
 * @property-read Collection<int, AgendaResolution> $resolutions
 */
#[Fillable(['game_id', 'submitted_by_character_id', 'title', 'body', 'control_note', 'status'])]
class AgendaCard extends Model
{
    /** @use HasFactory<AgendaCardFactory> */
    use HasFactory;

    /**
     * The bounds rulebook 3.1.4 puts on a card's list of resolutions.
     */
    public const MINIMUM_RESOLUTIONS = 2;

    public const MAXIMUM_RESOLUTIONS = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgendaCardStatus::class,
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * The player who wrote this card, for a custom agenda (3.1.3). Null for a
     * card Control wrote into the deck.
     *
     * @return BelongsTo<Character, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'submitted_by_character_id');
    }

    /** @return HasMany<AgendaResolution, $this> */
    public function resolutions(): HasMany
    {
        return $this->hasMany(AgendaResolution::class)->orderBy('position');
    }

    /** @return HasMany<CouncilAgendaItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CouncilAgendaItem::class);
    }

    /**
     * The resolutions the Council may actually vote for: everything that is not
     * removed and not a proposed addition still waiting on Control.
     *
     * @return Collection<int, AgendaResolution>
     */
    public function votableResolutions(): Collection
    {
        return $this->resolutions
            ->filter(fn (AgendaResolution $resolution): bool => $resolution->isVotable())
            ->values();
    }

    public function isCustom(): bool
    {
        return $this->submitted_by_character_id !== null;
    }
}
