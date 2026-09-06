<?php

namespace App\Support\Runs;

/**
 * How strong a Protection Card is by the time the Runners reach it, and why.
 *
 * Rulebook 3.4.2 escalates a card from three independent directions and they
 * stack: how far into the Facility the Runners have got, how many Alerts are
 * standing, and whatever Security has paid to Boost this particular card. The
 * printed strength is only the floor.
 *
 * The breakdown is returned rather than just the total, because the total on
 * its own is unarguable in the wrong way. A Runner told "strength 6" will ask
 * where that came from, and the answer - printed 3, +1 for the four cards you
 * are past, +1 at three Alerts, +1 Boost - is the difference between a ruling
 * and an argument. It goes in the event log for the same reason.
 */
readonly class ChallengeStrength
{
    public function __construct(
        /** What the card itself says, before anything happens to it. */
        public int $printed,
        /** +1 per 2 Active cards already passed. */
        public int $fromCardsPassed,
        /** The Alert curve. */
        public int $fromAlerts,
        /** Credits Security has spent making this card harder. */
        public int $fromBoosts,
    ) {}

    /**
     * Work out a card's strength where the Runners have got to.
     *
     * The printed strength is a parameter rather than something read off the
     * card, because there is no parsed strength column to read: a challenge is
     * the sentence the card prints - "Brute (6)", "Brute/Hack (2)", "Hack (4+N)
     * - where N is the number of cards underneath this" - and the table
     * converts it. So whoever is running the card names the number, and this
     * says what happens to it.
     */
    public static function for(
        int $printed,
        int $cardsPassed,
        int $alerts,
        int $boosts,
        ?int $alertOverride = null,
    ): self {
        return new self(
            printed: max(0, $printed),
            fromCardsPassed: self::fromCardsPassed($cardsPassed),
            fromAlerts: AlertSchedule::strengthBonus($alerts, $alertOverride),
            fromBoosts: max(0, $boosts),
        );
    }

    /**
     * +1 for every 2 Active Protection Cards already passed.
     *
     * The rulebook works it through card by card and it is worth checking the
     * arithmetic against that, because the off-by-one is easy: the first and
     * second cards get nothing, the third and fourth get +1, the fifth and
     * sixth get +2. Counted from cards *passed* rather than the card's own
     * position, that is simply half, rounded down - facing the third card means
     * two are behind you.
     */
    public static function fromCardsPassed(int $cardsPassed): int
    {
        return intdiv(max(0, $cardsPassed), 2);
    }

    /**
     * The number of d8s Security rolls against the Runners.
     */
    public function total(): int
    {
        return $this->printed + $this->fromCardsPassed + $this->fromAlerts + $this->fromBoosts;
    }

    /**
     * The breakdown, for the event log and for the screen.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'printed' => $this->printed,
            'cards_passed' => $this->fromCardsPassed,
            'alerts' => $this->fromAlerts,
            'boosts' => $this->fromBoosts,
            'total' => $this->total(),
        ];
    }

    /**
     * The same thing in a sentence, naming only what actually contributed.
     */
    public function explain(): string
    {
        $parts = ["{$this->printed} printed"];

        if ($this->fromCardsPassed > 0) {
            $parts[] = "+{$this->fromCardsPassed} for cards passed";
        }

        if ($this->fromAlerts > 0) {
            $parts[] = "+{$this->fromAlerts} from Alerts";
        }

        if ($this->fromBoosts > 0) {
            $parts[] = "+{$this->fromBoosts} Boosted";
        }

        return sprintf('%d — %s', $this->total(), implode(', ', $parts));
    }
}
