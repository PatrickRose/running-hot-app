<?php

namespace App\Models;

use App\Enums\StockCertificateOption;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A share in a Corporation's Income (rulebook 3.4.3).
 *
 * A Runner who gets into a Corporate Facility may spend an access on stealing
 * one, and Control hands it over. It can be sold on - "to Corporations or other
 * characters" - and cashed in once, and cashing it is the one thing about it
 * the application works out rather than leaves to the table.
 *
 * Written by App\Services\StockCertificateService and by nothing else.
 *
 * @property int $id
 * @property int $game_id
 * @property int $corporation_id
 * @property int|null $character_id
 * @property StockCertificateOption|null $cashed_as
 * @property int|null $credits_paid
 * @property Carbon|null $cashed_at
 * @property int|null $cashed_by_character_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read Corporation $corporation
 * @property-read Character|null $holder
 * @property-read Character|null $cashedBy
 */
#[Fillable(['game_id', 'corporation_id', 'character_id'])]
class StockCertificate extends Model
{
    /**
     * The words on the card, which is the whole of its rules.
     */
    public const TEXT = 'Take a half (rounded down) of the corporation income as credits and reduce '
        .'their income by 1, or take a quarter (rounded up) of the corporation\'s income as credits.';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cashed_as' => StockCertificateOption::class,
            'credits_paid' => 'integer',
            'cashed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Corporation, $this> */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_id');
    }

    /** @return BelongsTo<Character, $this> */
    public function cashedBy(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'cashed_by_character_id');
    }

    public function isCashed(): bool
    {
        return $this->cashed_at !== null;
    }
}
