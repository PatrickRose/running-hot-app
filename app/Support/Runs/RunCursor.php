<?php

namespace App\Support\Runs;

use App\Enums\RunStep;
use App\Models\FacilityCardActivation;
use App\Models\FacilityProtectionCard;

/**
 * Where a run has got to: which card, which pass, which step.
 *
 * Derived from the event log rather than stored, for the reason the Council
 * reads the Chair's choice off the cards rather than a flag: a cursor kept
 * beside an append-only log is a second source of truth that can disagree with
 * it, and the log is the one players will be shown.
 *
 * A pass is one trip round Activate, Challenge, Consequence, Breather. A Retry
 * starts a new pass against the same card, which is why the pass number and the
 * count of cards passed are different things.
 */
readonly class RunCursor
{
    public function __construct(
        public int $pass,
        public RunStep $step,
        /** The card the Runners are facing, or null when the stacks are exhausted. */
        public ?FacilityProtectionCard $card,
        /** This turn's activation row for that card, if one has been written. */
        public ?FacilityCardActivation $activation,
        /** How many cards are left, this one included. */
        public int $cardsRemaining,
    ) {}

    /**
     * Whether the card in front of the Runners is switched on.
     *
     * An absent row and an unactivated row both mean Inactive; they differ in
     * whether Security has had its go, which is what {@see self::$activation}
     * is for rather than this.
     */
    public function cardIsActive(): bool
    {
        return $this->activation?->isActive() ?? false;
    }

    /**
     * Whether Security has already had its say about this card, this pass.
     *
     * A card activated by an earlier group is already Active when this group
     * arrives (3.4.2), so the answer can be yes before this run has done
     * anything at all - which is exactly what going second means.
     */
    public function activationSettled(): bool
    {
        return $this->activation !== null;
    }

    /**
     * Whether there is a card to face at all.
     */
    public function hasCard(): bool
    {
        return $this->card !== null;
    }
}
