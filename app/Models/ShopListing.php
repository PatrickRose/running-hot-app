<?php

namespace App\Models;

use App\Enums\ShopListingStatus;
use Database\Factories\ShopListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line of the shop's list: a card, a price, and what is left of it
 * (rulebook 3.3.3).
 *
 * The card is a morph because the two counters sell different things to
 * different people out of different purses - a Protection Card to a Security
 * player out of the Corporation's Credits, an Equipment card to a Runner out of
 * their own. Which of the two a line holds is therefore the only thing anybody
 * needs to know to work out how a sale goes, and App\Services\ShopService is
 * where that is read.
 *
 * Nothing here decides anything. Whether a line can be bought from is the
 * status and the stock together, which the service asks about in one place, and
 * whether *you* may buy from it is App\Policies\ShopPolicy's answer.
 *
 * @property int $id
 * @property int $game_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property int $price
 * @property int|null $stock
 * @property ShopListingStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read ProtectionCardType|EquipmentCardType|null $stockable
 */
#[Fillable(['game_id', 'stockable_type', 'stockable_id', 'price', 'stock', 'status', 'notes'])]
class ShopListing extends Model
{
    /** @use HasFactory<ShopListingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShopListingStatus::class,
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return MorphTo<Model, $this> */
    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<ShopPurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(ShopPurchase::class);
    }

    /**
     * Whether this line has run out.
     *
     * A line with no stock figure never does: that is Control saying the shop
     * has as many of these as anybody wants, which is a perfectly ordinary
     * thing to say about a basic card.
     */
    public function isSoldOut(): bool
    {
        return $this->stock !== null && $this->stock <= 0;
    }

    /**
     * Whether a copy could be sold right now, leaving aside who is asking.
     */
    public function isAvailable(): bool
    {
        return $this->status->isBuyable() && ! $this->isSoldOut();
    }

    /**
     * Whether this line sells Protection Cards, which decides everything about
     * how a sale goes: who may buy, which purse pays, and where the copy lands.
     */
    public function isProtectionCard(): bool
    {
        return $this->stockable_type === (new ProtectionCardType)->getMorphClass();
    }

    public function isEquipment(): bool
    {
        return $this->stockable_type === (new EquipmentCardType)->getMorphClass();
    }
}
