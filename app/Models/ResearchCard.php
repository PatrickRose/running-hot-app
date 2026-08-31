<?php

namespace App\Models;

use App\Enums\ResearchSuit;
use App\Enums\ResearchZone;
use App\Support\EquationCard;
use App\Support\IconFont;
use Database\Factories\ResearchCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One card in the research game (rulebook 3.2.1).
 *
 * A card instance, not a card type: a research card is a suit and a value and
 * nothing else, so there is no catalogue to point at. Two 3-of-Leaf cards are
 * two rows, which is what lets deck customisation add one and Control upgrade
 * one without either touching its twin.
 *
 * A null suit is a wild card rather than a card whose suit is unknown - "some
 * cards are marked as wild and can be used as any type".
 *
 * A null corporation_id is the shared public deck, which belongs to the game.
 * App\Services\ResearchTableService owns every write to zone and position, so
 * nothing else shuffles, deals or spends a card.
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $corporation_id
 * @property ResearchSuit|null $suit
 * @property int $value
 * @property ResearchZone $zone
 * @property int $position
 * @property string|null $restriction
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Corporation|null $corporation
 */
#[Fillable([
    'game_id', 'corporation_id', 'suit', 'value', 'zone', 'position', 'restriction',
])]
class ResearchCard extends Model
{
    /** @use HasFactory<ResearchCardFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suit' => ResearchSuit::class,
            'zone' => ResearchZone::class,
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * The Corporation whose deck this is, or null for the public deck.
     *
     * @return BelongsTo<Corporation, $this>
     */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    public function isWild(): bool
    {
        return $this->suit === null;
    }

    public function isPublic(): bool
    {
        return $this->corporation_id === null;
    }

    /**
     * What this card is called on a page: "7 Leaf", or "Wild 4".
     */
    public function label(): string
    {
        return $this->isWild()
            ? sprintf('Wild %d', $this->value)
            : sprintf('%d %s', $this->value, $this->suit->label());
    }

    /**
     * The character that draws this card's icon in the game's own font.
     *
     * A wild card has one of its own: the font's Y, which was drawn for this
     * and had nothing to show it until now.
     */
    public function glyph(): string
    {
        return $this->suit?->glyph() ?? IconFont::WILDCARD;
    }

    /**
     * This card as the equation rules see it.
     *
     * The rules are pure and know nothing about zones, so whether the card came
     * out of a hand is passed in rather than read off the row: a public pool
     * card is never "from hand" however the player got hold of it.
     */
    public function toEquationCard(bool $fromHand): EquationCard
    {
        return new EquationCard($this->suit, $this->value, $fromHand, $this->id);
    }

    /**
     * @param  Builder<ResearchCard>  $query
     * @return Builder<ResearchCard>
     */
    public function scopeInZone(Builder $query, ResearchZone $zone): Builder
    {
        return $query->where('zone', $zone);
    }

    /**
     * The public deck and pool, which belong to no Corporation.
     *
     * @param  Builder<ResearchCard>  $query
     * @return Builder<ResearchCard>
     */
    public function scopePublicCards(Builder $query): Builder
    {
        return $query->whereNull('corporation_id');
    }
}
