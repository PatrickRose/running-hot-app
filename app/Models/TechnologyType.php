<?php

namespace App\Models;

use App\Enums\ResearchSuit;
use App\Support\CardImage;
use App\Support\TechnologyBlueprint;
use Database\Factories\TechnologyTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A technology on a Corporation's tech tree (rulebook 3.2.2).
 *
 * The catalogue only: what can be researched and what it costs. Which
 * Corporation has researched one, and which Facility stores the resulting card,
 * belong to the research game and to Facility storage, neither of which exists
 * yet.
 *
 * A technology's effect is the text printed on it, including the mechanical ones
 * ("Unlock: Keresh", "Unlock: Power facility"). Nothing acts on them: the
 * research game that would is not built, so Control reads the words, exactly as
 * they do with a Facility type's effect.
 *
 * @property int $id
 * @property int $game_id
 * @property string|null $code
 * @property string $name
 * @property string|null $split_group
 * @property int|null $split_piece
 * @property int|null $split_pieces
 * @property string $tree
 * @property int|null $corporation_id
 * @property string|null $description
 * @property string|null $effect
 * @property int $cog_cost
 * @property int $brain_cost
 * @property int $leaf_cost
 * @property int $maths_cost
 * @property array<int, string> $prerequisites
 * @property int|null $required_facility_type_id
 * @property int|null $copy_strength
 * @property int|null $destroy_strength
 * @property array<string, mixed>|null $deck_grant
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id', 'code', 'name', 'tree', 'corporation_id',
    'split_group', 'split_piece', 'split_pieces',
    'description', 'effect',
    'cog_cost', 'brain_cost', 'leaf_cost', 'maths_cost',
    'prerequisites', 'required_facility_type_id',
    'copy_strength', 'destroy_strength', 'deck_grant', 'notes',
])]
class TechnologyType extends Model
{
    /** @use HasFactory<TechnologyTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prerequisites' => 'array',
            'deck_grant' => 'array',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * The Corporation whose tree this sits on, or null for the technologies
     * common to every Corporation.
     *
     * Also null where the tree belongs to a Corporation this game does not have,
     * because Control built their own roster. The tree column still says which
     * one it came from.
     *
     * @return BelongsTo<Corporation, $this>
     */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    /**
     * Where the resulting card has to be housed, if the card names a type.
     *
     * @return BelongsTo<FacilityType, $this>
     */
    public function requiredFacilityType(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class, 'required_facility_type_id');
    }

    /**
     * The cards of this technology a Corporation has (rulebook 3.2.2).
     *
     * @return HasMany<TechnologyHolding, $this>
     */
    public function holdings(): HasMany
    {
        return $this->hasMany(TechnologyHolding::class);
    }

    /**
     * Whether every Corporation's tree carries this technology.
     */
    public function isCommon(): bool
    {
        return $this->tree === TechnologyBlueprint::COMMON;
    }

    /**
     * The price in each of the four Research Point suits.
     *
     * Given as the full set rather than only the suits that cost something, so a
     * caller showing a price shows four columns and not a ragged row. A suit
     * costing nothing is a real price.
     *
     * @return array<string, int>
     */
    public function cost(): array
    {
        $cost = [];

        foreach (ResearchSuit::all() as $suit) {
            $cost[$suit->value] = (int) $this->{$suit->costColumn()};
        }

        return $cost;
    }

    /**
     * Whether researching this costs no Research Points at all.
     *
     * True of the starting technologies each Corporation opens with, which are
     * on the tree so their split pieces can be tracked rather than because
     * anybody has to pay for them.
     */
    public function isFree(): bool
    {
        return array_sum($this->cost()) === 0;
    }

    /**
     * Whether this technology is one card of several (rulebook 3.2.7).
     *
     * Read off the printed name - "Power (Part 1/4)" - at seed time, so a
     * technology Control writes during play is split if they name it that way
     * and single if they do not.
     */
    public function isSplit(): bool
    {
        return $this->split_group !== null;
    }

    /**
     * Whether researching this is deck customisation rather than a technology
     * (rulebook 3.2.3).
     */
    public function isDeckCustomisation(): bool
    {
        return $this->deck_grant !== null;
    }

    /**
     * What deck customisation this row grants and what it asks for, as its own
     * card prints it (rulebook 3.2.3).
     *
     * The six "Research deck" rows on the common tree do not price like
     * anything else on the tree - "spend 4 research credits in any suit", "6 in
     * any suit and 3 in another" - so the amounts are a list the player assigns
     * to suits of their choosing, and the card that comes out is described
     * rather than fixed: a value the player picks inside a range, wild or not.
     *
     * @return array{
     *     amounts: array<int, int>,
     *     value_min: int,
     *     value_max: int,
     *     wild: bool,
     *     restriction: string|null,
     *     requires_research_facilities: int,
     * }|null
     */
    public function deckGrant(): ?array
    {
        $grant = $this->deck_grant;

        if ($grant === null) {
            return null;
        }

        return [
            'amounts' => array_values(array_map('intval', $grant['amounts'] ?? [])),
            'value_min' => (int) ($grant['value_min'] ?? 1),
            'value_max' => (int) ($grant['value_max'] ?? 1),
            'wild' => (bool) ($grant['wild'] ?? false),
            'restriction' => $grant['restriction'] ?? null,
            'requires_research_facilities' => (int) ($grant['requires_research_facilities'] ?? 0),
        ];
    }

    /**
     * The web path to this card's artwork, or null where there is none.
     */
    public function imagePath(): ?string
    {
        return CardImage::pathFor($this->code);
    }

    /**
     * The other face of the card.
     *
     * A research card is printed proposal side up and is flipped over when the
     * Corporation researches it (rulebook 3.2.2), so it has two. Both are
     * public - a technology is not secret, only which Facility is storing it is
     * - so nothing here decides who may see which side.
     */
    public function backImagePath(): ?string
    {
        return CardImage::pathFor($this->code, CardImage::BACK);
    }
}
