<?php

namespace App\Http\Requests;

use App\Enums\ResearchCardMarking;
use App\Enums\ResearchSuit;
use App\Support\CardMarking;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Reading a card's markings off a form.
 *
 * Two forms ask for them - the card Control edits and the deck customisation
 * row Control writes - and they have to agree, because a marking that reached
 * App\Support\CardMarking malformed would throw out of a constructor rather
 * than come back as a message on the field.
 *
 * The pairing is the whole of what needs checking: Restricted names the suit
 * the other side must be and is meaningless without it, and No single names
 * none. Neither is dropped quietly, because a card that silently lost half its
 * marking would go on being played wrongly all game with nothing to say why.
 */
trait ShapesCardMarkings
{
    /**
     * @return array<string, mixed>
     */
    protected function markingRules(string $key = 'markings'): array
    {
        return [
            $key => ['nullable', 'array', 'max:'.count(ResearchCardMarking::all()), $this->markingsPair()],
            $key.'.*.marking' => ['required', Rule::enum(ResearchCardMarking::class)],
            $key.'.*.suit' => ['nullable', Rule::enum(ResearchSuit::class)],
        ];
    }

    /**
     * A marking and its suit have to belong together.
     */
    private function markingsPair(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $marking = ResearchCardMarking::tryFrom((string) ($entry['marking'] ?? ''));
                $suit = (string) ($entry['suit'] ?? '');

                if ($marking === null) {
                    continue;
                }

                if ($marking->namesASuit() && $suit === '') {
                    $fail(sprintf(
                        'A card marked "%s" has to name the suit the other side must be.',
                        $marking->label(),
                    ));
                }

                if (! $marking->namesASuit() && $suit !== '') {
                    $fail(sprintf(
                        'A card marked "%s" names no suit.',
                        $marking->label(),
                    ));
                }
            }
        };
    }

    /**
     * Throw away the slots a form left empty.
     *
     * A marking form has a slot per kind, and an untouched one posts an empty
     * suit rather than nothing at all - so a blank Restricted is somebody who
     * did not use the field, not somebody who wrote half a marking. Dropping it
     * here keeps that judgement at the HTTP edge: App\Support\CardMarking still
     * refuses a half-written marking, and so does the deck seeder, where a blank
     * suit really is a mistake in a file somebody wrote by hand.
     *
     * @param  mixed  $submitted
     * @return array<int, array<string, mixed>>
     */
    protected function filterBlankMarkings($submitted): array
    {
        if (! is_array($submitted)) {
            return [];
        }

        $kept = [];

        foreach ($submitted as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $marking = ResearchCardMarking::tryFrom((string) ($entry['marking'] ?? ''));

            if ($marking === null) {
                continue;
            }

            if ($marking->namesASuit() && (string) ($entry['suit'] ?? '') === '') {
                continue;
            }

            $kept[] = $entry;
        }

        return $kept;
    }

    /**
     * The markings a form submitted, as objects.
     *
     * @param  array<int, mixed>  $submitted
     * @return array<int, CardMarking>
     */
    protected function shapeMarkings(array $submitted): array
    {
        $markings = [];

        foreach ($submitted as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $marking = ResearchCardMarking::tryFrom((string) ($entry['marking'] ?? ''));

            if ($marking === null) {
                continue;
            }

            $suit = (string) ($entry['suit'] ?? '');

            $markings[] = new CardMarking(
                $marking,
                $marking->namesASuit() ? ResearchSuit::from($suit) : null,
            );
        }

        return $markings;
    }
}
