<?php

namespace App\Services;

use Random\RandomException;
use RuntimeException;

/**
 * Real dice, from the platform's cryptographic source.
 *
 * random_int() rather than mt_rand() not because a Run needs cryptographic
 * randomness, but because mt_rand() is seedable and predictable, and this
 * rolls for both sides of a contest between players. The cost is nothing at
 * the handful of dice a Run throws.
 */
class RandomDice implements Dice
{
    /**
     * @param  int<0, max>  $count
     * @param  int<2, max>  $faces
     * @return array<int, int>
     */
    public function roll(int $count, int $faces): array
    {
        $rolled = [];

        for ($die = 0; $die < $count; $die++) {
            try {
                $rolled[] = random_int(1, $faces);
            } catch (RandomException $exception) {
                // The platform has no entropy left, which is not something a
                // Run can carry on through: a roll nobody can trust is worse
                // than a roll that did not happen.
                throw new RuntimeException('Could not roll the dice.', previous: $exception);
            }
        }

        return $rolled;
    }
}
