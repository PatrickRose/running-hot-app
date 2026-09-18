<?php

namespace App\Support\Runs;

use InvalidArgumentException;

/**
 * What an Equipment card is doing to a roll, as the player reading it says.
 *
 * The Runners' pool is derived - the Leader's full skill, half of everybody
 * else's, a smaller die while Wounded - and the dice are thrown on the server
 * so a browser cannot decide it won. Which leaves nowhere for an Equipment card
 * to act: "+2 Brute", "The next time you roll dice, roll d8s", "Gain +3 dice for
 * this roll but -1 dice for the next one" and Mind jack's "Retry any failed
 * rolls once" are all instructions to the dice, and the dice are ours.
 *
 * So the *effect is not parsed* - what a card does is printed text, exactly as a
 * Facility type's effect and a technology's are, and encoding all seventy-four
 * into a little language would be a second rulebook to keep in step. What is
 * taken instead is what the player, holding the card, says it grants this roll.
 * Three shapes cover every dice-facing card on the sheet:
 *
 * - **dice**, plus or minus, which covers "+2 Brute", "+1 die for next roll"
 *   and the minus half of "Gain +3 dice for this roll but -1 dice for the next
 *   one"
 * - **die size**, which covers "Roll d8 for Brute" and "The next time you roll
 *   dice, roll d8s" - both of which are a Wounded Runner buying their d8s back
 * - **rerolling failures once**, which is Mind jack
 *
 * The card played is recorded separately, so the log says which card was in
 * somebody's hand when the pool changed size. This says only what the dice did.
 */
readonly class RollModifiers
{
    /** The die sizes the game's own cards name. */
    public const ALLOWED_FACES = [4, 6, 8, 10, 12];

    public function __construct(
        public int $dice = 0,
        public ?int $dieFaces = null,
        public bool $rerollFailures = false,
    ) {
        if ($dieFaces !== null && ! in_array($dieFaces, self::ALLOWED_FACES, true)) {
            throw new InvalidArgumentException(sprintf('A d%d is not a die this game rolls.', $dieFaces));
        }
    }

    public static function none(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->dice === 0 && $this->dieFaces === null && ! $this->rerollFailures;
    }

    /**
     * How many dice are actually thrown.
     *
     * Never below zero, and never below one while the pool had any: a Runner
     * who has been talked into a card that costs them dice still rolls, because
     * "-1 die" on a pool of one is a bad trade rather than an impossibility.
     * A pool that was already empty stays empty.
     *
     * @param  int<0, max>  $pool
     * @return int<0, max>
     */
    public function diceFrom(int $pool): int
    {
        if ($pool === 0) {
            return 0;
        }

        return max(1, $pool + $this->dice);
    }

    /**
     * @param  int<2, max>  $faces
     * @return int<2, max>
     */
    public function facesFrom(int $faces): int
    {
        /** @var int<2, max> $chosen */
        $chosen = $this->dieFaces ?? $faces;

        return $chosen;
    }

    /**
     * The same thing in a sentence, naming only what was actually changed.
     */
    public function explain(): string
    {
        $parts = [];

        if ($this->dice !== 0) {
            $parts[] = sprintf('%+d %s', $this->dice, abs($this->dice) === 1 ? 'die' : 'dice');
        }

        if ($this->dieFaces !== null) {
            $parts[] = sprintf('rolled as d%d', $this->dieFaces);
        }

        if ($this->rerollFailures) {
            $parts[] = 'failures rerolled once';
        }

        return $parts === [] ? 'nothing' : implode(', ', $parts);
    }

    /**
     * @return array{dice: int, die_faces: int|null, reroll_failures: bool}
     */
    public function toArray(): array
    {
        return [
            'dice' => $this->dice,
            'die_faces' => $this->dieFaces,
            'reroll_failures' => $this->rerollFailures,
        ];
    }
}
