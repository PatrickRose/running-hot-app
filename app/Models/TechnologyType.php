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
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id', 'code', 'name', 'tree', 'corporation_id',
    'description', 'effect',
    'cog_cost', 'brain_cost', 'leaf_cost', 'maths_cost',
    'prerequisites', 'required_facility_type_id',
    'copy_strength', 'destroy_strength', 'notes',
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
