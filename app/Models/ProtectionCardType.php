<?php

namespace App\Models;

use App\Enums\ProtectionCardAvailability;
use App\Enums\ProtectionKind;
use App\Enums\RunnerSkill;
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
 * @property int $id
 * @property int $game_id
 * @property string $name
 * @property ProtectionKind $kind
 * @property int $cost
 * @property RunnerSkill $challenge_skill
 * @property int $challenge_strength
 * @property string $consequence
 * @property int|null $charge_cost
 * @property string|null $charge_consequence
 * @property ProtectionCardAvailability $availability
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id', 'name', 'kind', 'cost',
    'challenge_skill', 'challenge_strength', 'consequence',
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
            'challenge_skill' => RunnerSkill::class,
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

    /**
     * Whether this card has a Charge, which Security may only use where they
     * are Directing Security (rulebook 3.3.5).
     */
    public function hasCharge(): bool
    {
        return $this->charge_consequence !== null;
    }
}
