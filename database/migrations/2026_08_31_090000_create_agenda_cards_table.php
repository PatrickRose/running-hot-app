<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Council's agenda cards (rulebook 3.1).
 *
 * A card is written either by Control, into the game's deck, or by a player
 * during Setup as a custom agenda (3.1.3). Both kinds are rows here and differ
 * only in where they start and who wrote them: a deck card begins in the deck
 * waiting to be drawn, a player's begins as a draft in their hands.
 *
 * Nothing seeds this table. The rulebook prints no agenda cards and the game's
 * own deck is not in this repository, so Control authors the deck for the game
 * it is running - the same way it invents a Facility type mid-game.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            // Null for a card Control wrote into the deck. Set for a custom
            // agenda, because a rejected card goes back to the player who wrote
            // it and they may take it to a future Chair (3.1.3).
            $table->foreignId('submitted_by_character_id')->nullable()
                ->constrained('characters')->nullOnDelete();

            $table->string('title');
            $table->text('body')->nullable();

            // Control's remarks, added on the way from the player to the Chair.
            // Stored apart from the body so that the annotation stays visibly
            // Control's rather than becoming part of what the player wrote.
            $table->text('control_note')->nullable();

            // Where the card is: in the deck, with Control, with the Chair, on
            // the table, discarded, voted. See App\Enums\AgendaCardStatus.
            $table->string('status')->index();

            $table->timestamps();

            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_cards');
    }
};
