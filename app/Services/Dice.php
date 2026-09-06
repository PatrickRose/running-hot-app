<?php

namespace App\Services;

/**
 * Somewhere to roll dice, so that rolling can be swapped out under a test.
 *
 * Every roll in a Run happens on the server. A browser that rolls its own dice
 * is a browser that can decide it won, and this is a game where players are
 * competing against each other for real stakes over an evening. It is also the
 * only way the faces can be kept, which is what answers "why did I lose that
 * check?" three turns later.
 *
 * An interface rather than a call to random_int() at the point of use, because
 * a test that cannot say what the dice did can only assert that something
 * happened. With this, a test says "Security rolls three successes" and then
 * asserts on the consequence, which is the part worth testing.
 */
interface Dice
{
    /**
     * Roll a pool, returning every face in the order it came up.
     *
     * A pool of zero is a legitimate roll and returns nothing: a Runner with no
     * skill in what the card asks for still faces it, and Security defending
     * with strength zero rolls nothing at all.
     *
     * @param  int<0, max>  $count
     * @param  int<2, max>  $faces
     * @return array<int, int>
     */
    public function roll(int $count, int $faces): array;
}
