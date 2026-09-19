<?php

namespace App\Http\Requests\Control;

use App\Enums\ShopListingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Control putting a card on the shop's list, or changing the line it is on
 * (rulebook 3.3.3).
 *
 * The family is asked for rather than guessed from the id, because the two
 * catalogues number from one apiece and a bare id could name either. It is also
 * what decides the whole shape of a sale, so it is worth saying out loud.
 */
class StockShopListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isControl() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'family' => ['required', Rule::in(['protection', 'equipment'])],
            'card_id' => ['required', 'integer'],
            // Nought is a real price: a card Control is handing out, which the
            // shop is as good a place for as any.
            'price' => ['required', 'integer', 'min:0', 'max:99999'],
            // Absent is a line that never runs out. Distinct from nought, which
            // is a line that has sold out.
            'stock' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::enum(ShopListingStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
