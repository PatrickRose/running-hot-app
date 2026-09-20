<?php

namespace App\Support;

use App\Enums\ResearchCardMarking;
use App\Enums\ResearchSuit;
use InvalidArgumentException;

/**
 * One marking as it is printed on one card (rulebook 3.2.1, 3.2.3).
 *
 * A kind and, where the kind needs one, the suit it names: No single stands
 * alone, and Restricted is only a rule once it says which suit the other side
 * has to be. Keeping the two together is what stops a "Restricted" with no suit
 * ever reaching App\Support\Equation, where it would be a rule that silently
 * checked nothing.
 *
 * A card carries a *list* of these rather than one, because the two markings
 * are about different halves of the equation and nothing stops a card printing
 * both: "No single" and "Other side must be Cog" on the same card means the set
 * holding it needs a second card and the far side has to be Cog.
 *
 * Immutable and pure, like everything else the equation rules are built out of.
 */
final readonly class CardMarking
{
    public function __construct(
        public ResearchCardMarking $marking,
        public ?ResearchSuit $suit = null,
    ) {
        if ($marking->namesASuit() && $suit === null) {
            throw new InvalidArgumentException(sprintf(
                'A card marked "%s" has to name the suit the other side must be.',
                $marking->label(),
            ));
        }

        if (! $marking->namesASuit() && $suit !== null) {
            throw new InvalidArgumentException(sprintf(
                'A card marked "%s" names no suit, so it cannot be given %s.',
                $marking->label(),
                $suit->label(),
            ));
        }
    }

    public static function noSingle(): self
    {
        return new self(ResearchCardMarking::NoSingle);
    }

    public static function restrictedTo(ResearchSuit $suit): self
    {
        return new self(ResearchCardMarking::Restricted, $suit);
    }

    /**
     * The words the card prints.
     */
    public function label(): string
    {
        return match ($this->marking) {
            ResearchCardMarking::NoSingle => 'No single',
            ResearchCardMarking::Restricted => sprintf(
                'Other side must be %s',
                $this->suit->label(),
            ),
        };
    }

    /**
     * The same marking in the space a card tile has for it.
     *
     * A research card is drawn 56 pixels wide, which is about five characters
     * of the smallest type the application uses - so "Other side must be Cog"
     * truncated to "Other side m..." and lost the one word that matters, which
     * is the suit. The short form says only the half that cannot be drawn, and
     * glyph() draws the other half as the suit's own icon beside it.
     */
    public function shortLabel(): string
    {
        return match ($this->marking) {
            ResearchCardMarking::NoSingle => 'No single',
            ResearchCardMarking::Restricted => 'Other',
        };
    }

    /**
     * The character that draws the suit this marking names, where it names one.
     *
     * Null for a marking that is only words, which is what keeps a caller from
     * drawing a blank icon for "No single".
     */
    public function glyph(): ?string
    {
        return $this->suit?->glyph();
    }

    /**
     * What the marking actually does, for a page with room to say so.
     */
    public function description(): string
    {
        return match ($this->marking) {
            ResearchCardMarking::NoSingle => 'Cannot be the only card on its side of an equation.',
            ResearchCardMarking::Restricted => sprintf(
                'The other side of the equation has to be %s.',
                $this->suit->label(),
            ),
        };
    }

    /**
     * The fewest cards allowed in the set holding this card.
     */
    public function minimumSetSize(): int
    {
        return $this->marking->minimumSetSize();
    }

    /**
     * The suit this marking demands of the *other* side, or null when it has
     * nothing to say about it.
     */
    public function demandsOfTheOtherSide(): ?ResearchSuit
    {
        return $this->marking === ResearchCardMarking::Restricted ? $this->suit : null;
    }

    /**
     * @return array{marking: string, suit: string|null}
     */
    public function toArray(): array
    {
        return [
            'marking' => $this->marking->value,
            'suit' => $this->suit?->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $marking
     */
    public static function fromArray(array $marking): self
    {
        $kind = ResearchCardMarking::from((string) ($marking['marking'] ?? ''));
        $suit = $marking['suit'] ?? null;

        return new self(
            $kind,
            $suit === null || $suit === '' ? null : ResearchSuit::from((string) $suit),
        );
    }

    /**
     * A stored list of markings, back as objects.
     *
     * @param  mixed  $markings
     * @return array<int, self>
     */
    public static function listFrom($markings): array
    {
        if (! is_array($markings)) {
            return [];
        }

        $shaped = [];

        foreach ($markings as $marking) {
            if (is_array($marking)) {
                $shaped[] = self::fromArray($marking);
            }
        }

        return $shaped;
    }

    /**
     * @param  array<int, self>  $markings
     * @return array<int, array{marking: string, suit: string|null}>
     */
    public static function listToArray(array $markings): array
    {
        return array_values(array_map(
            fn (self $marking): array => $marking->toArray(),
            $markings,
        ));
    }
}
