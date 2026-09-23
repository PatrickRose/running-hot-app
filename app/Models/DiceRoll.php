<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pool of d6s and d8s a player rolled for Control to read.
 *
 * Written by App\Actions\RollDice and by nothing else. The faces are rolled on
 * the server and kept, for the reason every roll in a Run is: a browser that
 * rolls its own dice is a browser that can decide it won, and a result nobody
 * can see the faces of is a result somebody argues with.
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $user_id
 * @property int|null $character_id
 * @property int $d6
 * @property int $d8
 * @property array{d6: array<int, int>, d8: array<int, int>} $faces
 * @property int $successes
 * @property string|null $purpose
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read User|null $user
 * @property-read Character|null $character
 */
#[Fillable(['game_id', 'user_id', 'character_id', 'd6', 'd8', 'faces', 'successes', 'purpose'])]
class DiceRoll extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'd6' => 'integer',
            'd8' => 'integer',
            'faces' => 'array',
            'successes' => 'integer',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
