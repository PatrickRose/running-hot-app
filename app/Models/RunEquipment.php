<?php

namespace App\Models;

use App\Enums\RunStep;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An Equipment card a Runner brought on a run, or played during one.
 *
 * One model for both, because the rulebook's three categories differ in *when*
 * a card is used rather than in what a used card is (3.4.1). A Permanent item
 * is equipped before the run begins and carries no pass or step; a This-run or
 * Single-use card is played "as you encounter Protection Cards" and records
 * which pass and step it was played in.
 *
 * That pair is what enforces 3.4.2's "Each Runner in the Runner group may use
 * one card during these steps", which the worked examples make per Runner per
 * *step* rather than per run: Ryan uses a Boost card during the Activate step
 * and "can not use another card until the next Activate step".
 *
 * @property int $id
 * @property int $run_id
 * @property int $character_id
 * @property int $equipment_card_type_id
 * @property int|null $pass
 * @property RunStep|null $step
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Run $run
 * @property-read Character $character
 * @property-read EquipmentCardType $cardType
 */
#[Fillable(['run_id', 'character_id', 'equipment_card_type_id', 'pass', 'step'])]
class RunEquipment extends Model
{
    protected $table = 'run_equipment';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => RunStep::class,
        ];
    }

    /**
     * Whether this was equipped before the run rather than played during it,
     * which is the same question as whether it is Permanent.
     */
    public function wasEquippedBeforehand(): bool
    {
        return $this->pass === null;
    }

    /** @return BelongsTo<Run, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return BelongsTo<EquipmentCardType, $this> */
    public function cardType(): BelongsTo
    {
        return $this->belongsTo(EquipmentCardType::class, 'equipment_card_type_id');
    }
}
