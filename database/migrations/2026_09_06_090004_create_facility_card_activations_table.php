<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether a Protection Card is warmed up, and how hard it has been made.
        //
        // This is the state that makes going second worse. Rulebook 3.4.2: all
        // cards start Inactive at the beginning of the Action Phase and stay
        // Active once activated "until the end of the Action Phase", and a
        // Boost lasts "for the rest of this phase" - so where two groups hit
        // the same Facility, the second meets cards the first paid to turn on
        // and Security paid to strengthen. That is why it hangs off the turn
        // rather than off the run.
        //
        // Per turn rather than per phase because a turn has exactly one Action
        // phase, and every other piece of Facility state - the security budget,
        // whether Security is Directing - is already scoped that way.
        Schema::create('facility_card_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_protection_card_id')->constrained()->cascadeOnDelete();

            // Null means Inactive: reached but not paid for, or not reached at
            // all. An Inactive card is skipped rather than fought (3.4.2), so
            // this is the difference between a card that costs the Runners a
            // challenge and one that costs them nothing.
            $table->timestamp('activated_at')->nullable();

            // What activating cost. Physical is always free; a cyber card costs
            // the number of cyber cards already active, so the first is free,
            // the second 1, the third 2. Recorded rather than recomputed
            // because the count it was charged against has moved on by the time
            // anyone asks.
            $table->unsignedInteger('activation_cost')->default(0);

            // Boosts are cumulative and priced 1, 2, 3... Credits each, and
            // each adds 1 to this card's challenge strength for the rest of the
            // phase. The count is what the next Boost is priced from, so it has
            // to be a number and not a flag.
            $table->unsignedInteger('boosts')->default(0);
            $table->unsignedInteger('boost_credits_spent')->default(0);

            $table->timestamps();

            // One row per card per turn. The card cannot be activated twice,
            // and this is what makes the second attempt a no-op rather than a
            // second charge.
            $table->unique(['turn_id', 'facility_protection_card_id'], 'card_activation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_card_activations');
    }
};
