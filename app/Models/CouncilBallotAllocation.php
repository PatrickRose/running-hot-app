<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How much Political Will one ballot puts behind one resolution.
 *
 * @property int $id
 * @property int $council_ballot_id
 * @property int $agenda_resolution_id
 * @property int $political_will
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CouncilBallot $ballot
 * @property-read AgendaResolution $resolution
 */
#[Fillable(['council_ballot_id', 'agenda_resolution_id', 'political_will'])]
class CouncilBallotAllocation extends Model
{
    /** @return BelongsTo<CouncilBallot, $this> */
    public function ballot(): BelongsTo
    {
        return $this->belongsTo(CouncilBallot::class, 'council_ballot_id');
    }

    /** @return BelongsTo<AgendaResolution, $this> */
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(AgendaResolution::class, 'agenda_resolution_id');
    }
}
