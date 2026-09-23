<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Game;
use App\Models\User;
use App\Services\Dice;
use Illuminate\Validation\ValidationException;

/**
 * Roll a pool of d6s and d8s and keep it for Control.
 *
 * The one rule is the game's own: a die showing 5 or better is a success,
 * whatever its size. Nothing else is decided here - what the successes were
 * needed for, and what they buy, is Control's to read off the roll and rule on.
 * That is why the result is recorded rather than announced: it is evidence for
 * a ruling, and the ruling is Control's.
 *
 * Rolled on the server through the same Dice binding a Run uses, so a test can
 * say what the dice did and a browser cannot.
 */
class RollDice
{
    /** The lowest face that counts as a success, on either die. */
    public const SUCCESS_ON = 5;

    /** The most of either die a single roll may throw. */
    public const MAX_PER_SIZE = 30;

    public function __construct(private readonly Dice $dice) {}

    public function handle(
        Game $game,
        int $d6,
        int $d8,
        ?User $user = null,
        ?Character $character = null,
        ?string $purpose = null,
    ): DiceRoll {
        if ($d6 < 0 || $d8 < 0 || $d6 + $d8 === 0) {
            throw ValidationException::withMessages([
                'd6' => 'Roll at least one die.',
            ]);
        }

        if ($d6 > self::MAX_PER_SIZE || $d8 > self::MAX_PER_SIZE) {
            throw ValidationException::withMessages([
                'd6' => sprintf('Roll at most %d of each die at once.', self::MAX_PER_SIZE),
            ]);
        }

        $faces = [
            'd6' => $this->dice->roll($d6, 6),
            'd8' => $this->dice->roll($d8, 8),
        ];

        return $game->diceRolls()->create([
            'user_id' => $user?->id,
            'character_id' => $character?->id,
            // Paused counts: a roll made while Control has stopped the clock
            // still belongs to the phase it was made in.
            'phase_id' => $game->currentPhase()?->id,
            'd6' => $d6,
            'd8' => $d8,
            'faces' => $faces,
            'successes' => self::successesIn([...$faces['d6'], ...$faces['d8']]),
            'purpose' => filled($purpose) ? trim($purpose) : null,
        ]);
    }

    /**
     * @param  array<int, int>  $faces
     */
    public static function successesIn(array $faces): int
    {
        return count(array_filter($faces, fn (int $face): bool => $face >= self::SUCCESS_ON));
    }
}
