<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The four printed stats on a character sheet.
 *
 * Deliberately not the three Trackers beside them on the same screen: Wounds,
 * Tags and Credits move during play and go through TrackerService so the ledger
 * can explain them later. These four are what a character *is* - a number
 * Control set when the roster was built, corrected here when it was typed wrong
 * or when the fiction changes somebody.
 */
class UpdateCharacterStatsRequest extends FormRequest
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
            'brawn' => ['required', 'integer', 'min:0', 'max:100'],
            'hack' => ['required', 'integer', 'min:0', 'max:100'],
            'charisma' => ['required', 'integer', 'min:0', 'max:100'],
            // A Body of zero would make a character incapacitated before they
            // had taken a Wound (rulebook 3.4.2), which is not a state the game
            // has anything to say about.
            'body' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
