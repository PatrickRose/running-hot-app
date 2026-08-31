<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One turn's sitting of the Council (rulebook 3.1).
 *
 * Opened when the turn's Setup phase starts, because both halves of the Council
 * hang off the turn: the agenda is established during Setup and voted on during
 * the Action phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained()->cascadeOnDelete();

            // Who holds the Chair this turn. Taken from the rotation the game
            // holds on the corporations themselves, and then stored here rather
            // than derived, so Control can hand the Chair to somebody else for
            // one turn without disturbing the order for every turn after it.
            $table->foreignId('chair_corporation_id')->nullable()
                ->constrained('corporations')->nullOnDelete();

            // The moment the Council goes into recess: five minutes into the
            // Setup phase by default (3.1.1), and absolute for the reason the
            // phase clock is absolute - the browser only counts down between
            // polls and must never be able to make it run long. Control moves
            // it directly, and a pause moves it with the phase.
            $table->timestamp('recess_at')->nullable();

            // When Control drew the three cards for the Chair. Null until they
            // have, which is what stops a Chair keeping two out of nothing.
            $table->timestamp('drawn_at')->nullable();

            $table->timestamps();

            $table->unique('turn_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_sessions');
    }
};
