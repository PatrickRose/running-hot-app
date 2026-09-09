<?php

namespace App\Http\Requests;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Run;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A group naming the Facility it is going at (rulebook 3.4.1).
 *
 * Whether this player may put in for a run at all is the RunPolicy's; what is
 * left here is that the Facility and the Runners belong to this game and that
 * the people named could actually go on a run. Whether they are already out on
 * one is RunEngine's to refuse, because that is a rule rather than a form
 * error - and the engine has to hold it anyway for Control's sake.
 */
class SubmitRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $game = $this->game();

        return $game !== null
            && ($this->user()?->can('submit', [Run::class, $game]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $game = $this->game();
        $gameId = $game === null ? 0 : $game->id;

        return [
            'facility_id' => [
                'required', 'integer',
                Rule::exists('facilities', 'id')->where('game_id', $gameId),
            ],
            // The Run Leader. A solo Runner is their own Leader (3.4), so this
            // is required whether or not anybody else is coming.
            'run_leader_character_id' => [
                'required', 'integer',
                Rule::exists('characters', 'id')
                    ->where('game_id', $gameId)
                    ->whereIn('role', [CharacterRole::Runner->value, CharacterRole::Freelancer->value]),
            ],
            'member_character_ids' => ['array'],
            'member_character_ids.*' => [
                'integer',
                Rule::exists('characters', 'id')
                    ->where('game_id', $gameId)
                    ->whereIn('role', [CharacterRole::Runner->value, CharacterRole::Freelancer->value]),
            ],
        ];
    }

    /**
     * The game a run is being submitted in.
     *
     * The running game rather than one named in the URL, for the same reason
     * the Facility board and the Council Chamber take theirs that way: a player
     * has one game on, and asking them to say which would be asking them
     * something they cannot get wrong.
     */
    public function game(): ?Game
    {
        return Game::query()
            ->where('status', GameStatus::Running)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, int>
     */
    public function memberCharacterIds(): array
    {
        return array_map('intval', $this->input('member_character_ids', []));
    }
}
