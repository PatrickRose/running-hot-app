<?php

namespace App\Models;

use App\Enums\ProtectionCardAvailability;
use App\Enums\ProtectionKind;
use App\Support\CardImage;
use Database\Factories\ProtectionCardTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A card in the game's Protection Card catalogue (rulebook 3.3.2).
 *
 * The challenge is the sentence printed on the card rather than a skill and a
 * number, because that is what the real cards say - see
 * App\Support\ProtectionCardBlueprint. Card titles repeat (Doppleganger is two
 * cards), so the code is what identifies one.
 *
 * @property int $id
 * @property int $game_id
 * @property string|null $code
 * @property string $name
 * @property ProtectionKind $kind
 * @property int|null $cost
 * @property string $challenge
 * @property string $consequence
 * @property int|null $charge_cost
 * @property string|null $charge_consequence
 * @property ProtectionCardAvailability $availability
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id', 'code', 'name', 'kind', 'cost',
    'challenge', 'consequence',
    'charge_cost', 'charge_consequence', 'availability', 'notes',
])]
class ProtectionCardType extends Model
{
    /** @use HasFactory<ProtectionCardTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProtectionKind::class,
            'availability' => ProtectionCardAvailability::class,
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<FacilityProtectionCard, $this> */
    public function installations(): HasMany
    {
        return $this->hasMany(FacilityProtectionCard::class);
    }

    /** @return HasMany<ProtectionCardHolding, $this> */
    public function holdings(): HasMany
    {
        return $this->hasMany(ProtectionCardHolding::class);
    }

    /**
     * The web path to this card's artwork, or null where there is none.
     *
     * A card Control invents mid-game has no code and so no artwork, and is
     * shown as its text instead.
     */
    public function imagePath(): ?string
    {
        return CardImage::pathFor($this->code);
    }

    /**
     * Whether this card has a Charge, which Security may only use where they
     * are Directing Security (rulebook 3.3.5).
     */
    public function hasCharge(): bool
    {
        return $this->charge_consequence !== null;
    }
}
