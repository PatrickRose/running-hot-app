<?php

namespace App\Models;

use Database\Factories\ShopPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One copy leaving the shop.
 *
 * Written by App\Services\ShopService and by nothing else, which is the rule
 * every holding in this application lives under. It exists so that the stock
 * count on a listing can be reconciled: the count says where the shelf ended
 * up, and these rows say how it got there, which is the same division
 * `tracker_adjustments` draws for every number Control argues about.
 *
 * Two people are recorded rather than one, and they are different questions.
 * The character is who stood at the counter; the corporation is whose Credits
 * paid, which only a Protection Card has - a Runner buys out of their own purse
 * and the corporation stays null. A purchase Control made on somebody's behalf
 * still names that somebody, because the card went to them.
 *
 * The price is kept on the row rather than read back off the listing. Control
 * raising a price next turn must not rewrite what was paid last turn, and a
 * refund has to hand back the number that was actually taken.
 *
 * @property int $id
 * @property int $game_id
 * @property int $shop_listing_id
 * @property int|null $phase_id
 * @property int|null $character_id
 * @property int|null $corporation_id
 * @property int $price_paid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShopListing $listing
 * @property-read Character|null $character
 * @property-read Corporation|null $corporation
 */
#[Fillable([
    'game_id', 'shop_listing_id', 'phase_id', 'character_id', 'corporation_id', 'price_paid',
])]
class ShopPurchase extends Model
{
    /** @use HasFactory<ShopPurchaseFactory> */
    use HasFactory;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<ShopListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(ShopListing::class, 'shop_listing_id');
    }

    /** @return BelongsTo<Phase, $this> */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return BelongsTo<Corporation, $this> */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }
}
