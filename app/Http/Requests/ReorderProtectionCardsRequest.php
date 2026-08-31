<?php

namespace App\Http\Requests;

use App\Enums\ProtectionKind;
use App\Models\Facility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A reorder names the whole stack in its new encounter order, outermost first.
 *
 * The cost is worked out from the difference rather than sent by the browser:
 * the client asking what something costs is the client deciding what it costs.
 * The same request shape serves the quote and the commit, because a quote the
 * commit would not honour is worse than no quote at all.
 */
class ReorderProtectionCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Facility $facility */
        $facility = $this->route('facility');

        return $this->user()?->can('defend', $facility) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Facility $facility */
        $facility = $this->route('facility');

        return [
            'kind' => ['required', Rule::enum(ProtectionKind::class)],
            'order' => ['required', 'array', 'min:1'],
            'order.*' => [
                'required', 'integer',
                Rule::exists('facility_protection_cards', 'id')
                    ->where('facility_id', $facility->id),
            ],
        ];
    }

    public function kind(): ProtectionKind
    {
        return ProtectionKind::from($this->string('kind')->toString());
    }

    /**
     * @return array<int, int>
     */
    public function order(): array
    {
        /** @var array<int, mixed> $order */
        $order = $this->input('order', []);

        return array_map('intval', array_values($order));
    }
}
