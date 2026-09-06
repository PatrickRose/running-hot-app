<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A card in front of one sitting of the Council: how it got there, whether the
 * vote on it is secret, and how it resolved.
 *
 * Where the card *is* stays on the card itself (drawn, tabled, discarded,
 * voted), so this row does not repeat it. What it adds is everything that is
 * true of the card only in this turn's sitting - the same card can be drawn in
 * one turn, discarded, and written again by nobody, but a card promoted from
 * the important pile is a different item every time it is promoted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_card_id')->constrained()->cascadeOnDelete();

            // Drawn from the deck, accepted as urgent, or promoted out of the
            // important pile. See App\Enums\AgendaItemSource.
            $table->string('source');

            // The Chair may withhold the breakdown of a vote (3.1.2). This is
            // "hidden from the other players, still visible to the Chair"
            // rather than hidden outright: the Chair receives the individual
            // votes either way and may leak them as they see fit.
            $table->boolean('secret')->default(false);
            $table->timestamp('secret_declared_at')->nullable();

            // The resolution that carried, once the Chair has resolved it.
            $table->foreignId('outcome_resolution_id')->nullable()
                ->constrained('agenda_resolutions')->nullOnDelete();
            $table->boolean('tie_broken')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['council_session_id', 'agenda_card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_agenda_items');
    }
};
