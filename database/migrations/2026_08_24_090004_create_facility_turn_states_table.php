<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everything a Facility tracks that resets when the turn does: where
        // Security is Directing (rulebook 3.3.5), the budget placed on this
        // Facility, and how many cards have been pulled out of it this turn
        // (which is what makes the first removal each turn free, 3.3.4).
        //
        // A row is created on demand, so a Facility nobody touched this turn
        // simply has none.
        Schema::create('facility_turn_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();

            $table->boolean('security_directed')->default(false);

            // The budget is escrowed: setting it moves Credits out of the
            // Corporation, and whatever is unspent comes back at the end of the
            // Action phase.
            $table->unsignedInteger('security_budget')->default(0);
            $table->unsignedInteger('security_budget_spent')->default(0);
            $table->timestamp('budget_returned_at')->nullable();

            $table->unsignedInteger('cards_removed')->default(0);

            $table->timestamps();

            $table->unique(['turn_id', 'facility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_turn_states');
    }
};
