<?php

namespace App\Http\Requests\Control;

use App\Enums\ResearchCardRestriction;
use App\Models\Game;
use App\Models\TechnologyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Control writing a technology onto a tech tree (rulebook 3.2.2).
 *
 * The rulebook asks for this outright: "Custom Technologies" (3.2.4) has players
 * writing their own research proposals during the game, which Research Control
 * prices and adds to the tree. So a tree has to grow mid-game without a
 * deployment.
 */
class StoreTechnologyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isControl() ?? false;
    }

    /**
     * Prerequisites are typed as one line and held as a list.
     *
     * They are the titles printed on the cards rather than foreign keys - a
     * player's proposal may name a technology that does not exist yet, and
     * Research Control is shown the prerequisite card rather than looking an id
     * up (3.2.2, footnote 6).
     */
    protected function prepareForValidation(): void
    {
        $prerequisites = $this->input('prerequisites');

        // Always a list, never absent: a technology with no prerequisites has
        // an empty one, and the column has no default to fall back on.
        $this->merge([
            'prerequisites' => match (true) {
                is_array($prerequisites) => array_values($prerequisites),
                is_string($prerequisites) => collect(preg_split('/[;\n]/', $prerequisites) ?: [])
                    ->map(fn (string $title): string => trim($title))
                    ->filter()
                    ->values()
                    ->all(),
                default => [],
            },
            'deck_grant' => $this->deckGrant(),
        ]);
    }

    /**
     * A proposal that adds a card to a research deck rather than producing a
     * technology (rulebook 3.2.3), normalised or dropped entirely.
     *
     * The amounts are what make it one: a row with none is an ordinary
     * technology, and the form sends a blank set of boxes for every one of
     * those. So the whole block collapses to null unless somebody has priced
     * it, and what survives is fully shaped rather than half-filled.
     *
     * @return array<string, mixed>|null
     */
    private function deckGrant(): ?array
    {
        /** @var array<string, mixed> $grant */
        $grant = is_array($this->input('deck_grant')) ? $this->input('deck_grant') : [];

        /** @var array<int, mixed> $given */
        $given = is_array($grant['amounts'] ?? null) ? $grant['amounts'] : [];

        $amounts = array_values(array_filter(
            array_map('intval', $given),
            fn (int $amount): bool => $amount > 0,
        ));

        if ($amounts === []) {
            return null;
        }

        $minimum = max(1, (int) ($grant['value_min'] ?? 1));

        return [
            'amounts' => $amounts,
            'value_min' => $minimum,
            'value_max' => max($minimum, (int) ($grant['value_max'] ?? $minimum)),
            'wild' => filter_var($grant['wild'] ?? false, FILTER_VALIDATE_BOOL),
            'restriction' => ResearchCardRestriction::tryFrom(
                trim((string) ($grant['restriction'] ?? ''))
            )?->value,
            'requires_research_facilities' => max(0, (int) ($grant['requires_research_facilities'] ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Game $game */
        $game = $this->route('game');

        /** @var TechnologyType|null $technology */
        $technology = $this->route('technology');

        return [
            'code' => [
                'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('technology_types', 'code')
                    ->where('game_id', $game->id)
                    ->ignore($technology?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            // Which tree it sits on, kept as the key from the card sheet even
            // where no Corporation of that name is in this game.
            'tree' => ['required', 'string', 'max:64'],
            'corporation_id' => [
                'nullable',
                Rule::exists('corporations', 'id')->where('game_id', $game->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'effect' => ['nullable', 'string', 'max:2000'],

            // The price in the four Research Point suits. Zero is a real price
            // rather than a missing one, and a technology costing nothing in
            // every suit is a starting technology rather than a free one.
            'cog_cost' => ['required', 'integer', 'min:0', 'max:1000'],
            'brain_cost' => ['required', 'integer', 'min:0', 'max:1000'],
            'leaf_cost' => ['required', 'integer', 'min:0', 'max:1000'],
            'maths_cost' => ['required', 'integer', 'min:0', 'max:1000'],

            'prerequisites' => ['present', 'array'],
            'prerequisites.*' => ['string', 'max:255'],

            // Where the resulting card has to be housed, if it names a type
            // (3.2.2, footnote 7).
            'required_facility_type_id' => [
                'nullable',
                Rule::exists('facility_types', 'id')->where('game_id', $game->id),
            ],

            // What a Runner has to beat to take it (3.2.6).
            'copy_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'destroy_strength' => ['nullable', 'integer', 'min:0', 'max:100'],

            // Deck customisation, where the proposal buys a research card rather
            // than a technology (3.2.3). Null for every ordinary row, and
            // normalised above - so what arrives here is either absent or whole.
            'deck_grant' => ['nullable', 'array'],
            'deck_grant.amounts' => ['required_with:deck_grant', 'array', 'min:1', 'max:4'],
            'deck_grant.amounts.*' => ['integer', 'min:1', 'max:1000'],
            'deck_grant.value_min' => ['required_with:deck_grant', 'integer', 'min:1', 'max:99'],
            'deck_grant.value_max' => ['required_with:deck_grant', 'integer', 'min:1', 'max:99'],
            'deck_grant.wild' => ['required_with:deck_grant', 'boolean'],
            'deck_grant.restriction' => ['nullable', Rule::enum(ResearchCardRestriction::class)],
            'deck_grant.requires_research_facilities' => ['required_with:deck_grant', 'integer', 'min:0', 'max:50'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
