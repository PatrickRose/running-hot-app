<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One sitting of the research game (rulebook 3.2.1).
        //
        // "During the Action Phase, research players should make their way to
        // the research table", so a session belongs to a turn: it opens when
        // Research Control deals, and it closes when the phase end is called.
        // Holding it against the turn rather than the phase means Control can
        // open one early or run it long without the clock fighting them.
        Schema::create('research_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('turn_id')->nullable()->constrained()->cascadeOnDelete();

            // Whose turn it is, as a seat order rather than a Corporation id,
            // so a seat that has left can be skipped without renumbering
            // everybody behind it. Null once every seat has left.
            $table->unsignedInteger('current_order')->nullable();

            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['game_id', 'closed_at']);
        });

        // A Corporation's seat at the table, and where it sits in the order.
        //
        // "A turn order will be decided by Research Control randomly" - so the
        // order is drawn once per session and then fixed, and a seat that
        // leaves ("You may choose to leave the research game if you wish")
        // keeps its number so the rotation does not shuffle under everyone.
        Schema::create('research_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corporation_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('order');

            // Set when a Corporation leaves the table, or when its deck runs
            // dry at the end of its turn. Both end its participation for the
            // phase; the reason is kept because they read differently to
            // Control watching the table.
            $table->timestamp('left_at')->nullable();
            $table->string('left_reason')->nullable();

            $table->timestamps();

            $table->unique(['research_session_id', 'corporation_id']);
            $table->unique(['research_session_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_seats');
        Schema::dropIfExists('research_sessions');
    }
};
