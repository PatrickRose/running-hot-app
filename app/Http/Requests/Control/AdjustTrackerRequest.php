<?php

namespace App\Http\Requests\Control;

use App\Enums\Tracker;
use App\Models\Game;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdjustTrackerRequest extends FormRequest
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
            'subject_type' => ['required', 'string', Rule::in(array_keys(Relation::morphMap()))],
            'subject_id' => ['required', 'integer', 'min:1'],
            'tracker' => ['required', Rule::enum(Tracker::class)],
            'mode' => ['required', Rule::in(['adjust', 'set'])],
            'value' => ['required', 'integer', 'min:-1000000', 'max:1000000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $subject = $this->subject();

                if ($subject === null) {
                    $validator->errors()->add('subject_id', 'That subject does not exist.');

                    return;
                }

                if (! $this->subjectBelongsToGame($subject)) {
                    $validator->errors()->add('subject_id', 'That subject belongs to a different game.');

                    return;
                }

                $expected = $this->tracker()->subjectClass();

                if (! $subject instanceof $expected) {
                    $validator->errors()->add('tracker', sprintf(
                        'The %s tracker cannot be applied to that subject.',
                        $this->tracker()->label(),
                    ));
                }
            },
        ];
    }

    public function tracker(): Tracker
    {
        return Tracker::from($this->string('tracker')->toString());
    }

    public function subject(): ?Model
    {
        $class = Relation::getMorphedModel($this->string('subject_type')->toString());

        if ($class === null) {
            return null;
        }

        /** @var class-string<Model> $class */
        return $class::query()->find($this->integer('subject_id'));
    }

    protected function subjectBelongsToGame(Model $subject): bool
    {
        /** @var Game $game */
        $game = $this->route('game');

        return $subject instanceof Game
            ? $subject->is($game)
            : (int) $subject->getAttribute('game_id') === $game->id;
    }
}
