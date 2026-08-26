<?php

namespace App\Models;

use App\Enums\EquipmentCategory;
use App\Support\CardImage;
use Database\Factories\EquipmentCardTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A card in the game's Equipment catalogue - the items Runners carry into a Run
 * (rulebook 3.4.1).
 *
 * What the card does is the text printed on it. Every one of these effects
 * belongs to the Run loop, which is not built, so nothing here is computed: the
 * category is the only attribute the application reads, because it decides when
 * the card may be played and whether it counts against the three items a Runner
 * may equip.
 *
 * Who owns which copies is deliberately not modelled. Runners buy equipment from
 * the market or from each other during the Setup phase, which is a conversation
 * at the table.
 *
 * @property int $id
 * @property int $game_id
 * @property string|null $code
 * @property string $name
 * @property EquipmentCategory $category
 * @property string $effect
 * @property int|null $cost
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['game_id', 'code', 'name', 'category', 'effect', 'cost', 'notes'])]
class EquipmentCardType extends Model
{
    /** @use HasFactory<EquipmentCardTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => EquipmentCategory::class,
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Whether the market sells this card.
     *
     * A card with no price is granted by a technology or a Facility's access
     * effect rather than bought - the bypass cards and the reconnaissance items
     * are the whole of it.
     */
    public function isOnSale(): bool
    {
        return $this->cost !== null;
    }

    /**
     * The web path to this card's artwork, or null where there is none.
     */
    public function imagePath(): ?string
    {
        return CardImage::pathFor($this->code);
    }
}
