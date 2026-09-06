<?php

namespace Tests\Support;

use App\Services\Dice;
use RuntimeException;

/**
 * Dice that land on whatever the test says.
 *
 * A test that cannot say what the dice did can only assert that something
 * happened. With this a test says "Security rolls three successes" and then
 * asserts on the consequence, which is the part worth testing.
 *
 * Faces are queued and taken in order. Running out throws rather than falling
 * back to random, because a test that quietly started rolling real dice would
 * fail intermittently and for a reason nobody would look for.
 */
class FakeDice implements Dice
{
    /** @var array<int, int> */
    private array $queued = [];

    /** @var array<int, array{count: int, faces: int, rolled: array<int, int>}> */
    public array $rolls = [];

    /**
     * @param  array<int, int>  $faces
     */
    public function __construct(array $faces = [])
    {
        $this->queued = array_values($faces);
    }

    /**
     * Queue the faces the next rolls will produce, in order.
     *
     * @param  array<int, int>  $faces
     */
    public function will(array $faces): self
    {
        $this->queued = [...$this->queued, ...array_values($faces)];

        return $this;
    }

    /**
     * Queue a whole roll that lands on the same face every time.
     *
     * The common case in a test: "these six dice all succeed".
     */
    public function willRoll(int $count, int $face): self
    {
        return $this->will(array_fill(0, $count, $face));
    }

    /**
     * @param  int<0, max>  $count
     * @param  int<2, max>  $faces
     * @return array<int, int>
     */
    public function roll(int $count, int $faces): array
    {
        $rolled = [];

        for ($die = 0; $die < $count; $die++) {
            if ($this->queued === []) {
                throw new RuntimeException(
                    "FakeDice ran out of faces: asked for {$count}d{$faces} and the queue is empty."
                );
            }

            $rolled[] = array_shift($this->queued);
        }

        $this->rolls[] = ['count' => $count, 'faces' => $faces, 'rolled' => $rolled];

        return $rolled;
    }
}
