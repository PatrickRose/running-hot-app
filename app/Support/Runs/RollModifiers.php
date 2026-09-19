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
 * - **+1 to a die already rolled**, which is Armour's "Add +1 to one of your
 *   dice" - not a die added to the pool but a face nudged after it has been
 *   thrown, which is what turns a 4 into the success it was one short of
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
        /**
         * How many +1s the Runners are putting on dice they have already
         * rolled. One per die: the card says "one of your dice", so two cards
         * nudge two dice rather than stacking on one. Whether they may stack
         * is not printed anywhere and is Control's call.
         */
        public int $bumps = 0,
    ) {
        if ($bumps < 0) {
            throw new InvalidArgumentException('A die cannot be nudged downwards.');
        }

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
        return $this->dice === 0 && $this->dieFaces === null && $this->bumps === 0;
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
     * Put the +1s on the dice, once they have been thrown.
     *
     * Each goes on the highest die that is *not* yet a success, which is where
     * it can do something: a 4 one short of the threshold becomes a 5, and a 2
     * becomes a 3 and is still nothing. That is also optimal play, so applying
     * them here rather than asking takes no decision off anybody - a player
     * choosing for themselves would put them in exactly these places.
     *
     * A bump with nowhere useful to go is spent anyway rather than refused:
     * the card was played, and whether that was a waste is the player's
     * business.
     *
     * @param  array<int, int>  $faces
     * @return array<int, int>
     */
    public function bump(array $faces, int $successOn): array
    {
        if ($this->bumps < 1) {
            return $faces;
        }

        // Highest first, so the ones closest to succeeding are nudged first.
        $failing = array_keys(array_filter(
            $faces,
            static fn (int $face): bool => $face < $successOn,
        ));

        usort($failing, static fn (int $a, int $b): int => $faces[$b] <=> $faces[$a]);

        foreach (array_slice($failing, 0, $this->bumps) as $index) {
            $faces[$index]++;
        }

        return $faces;
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

        if ($this->bumps > 0) {
            $parts[] = sprintf(
                '%+d on %s',
                $this->bumps,
                $this->bumps === 1 ? 'one die' : sprintf('%d dice', $this->bumps),
            );
        }

        return $parts === [] ? 'nothing' : implode(', ', $parts);
    }

    /**
     * @return array{dice: int, die_faces: int|null, bumps: int}
     */
    public function toArray(): array
    {
        return [
            'dice' => $this->dice,
            'die_faces' => $this->dieFaces,
            'bumps' => $this->bumps,
        ];
    }
}
