<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every card in the research game (rulebook 3.2.1), private and public
        // alike.
        //
        // A card instance rather than a card type, because there is nothing to
        // look up: a research card is a suit and a value, printed on nothing
        // else. Two 3-of-Leaf cards are two rows, and deck customisation adding
        // a card is one more row rather than a count going up - which is what
        // lets Control upgrade a single card without touching its twin.
        Schema::create('research_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            // Whose deck this belongs to. Null is the shared public deck the
            // six-card pool is dealt from, which belongs to the game rather
            // than to anybody.
            $table->foreignId('corporation_id')->nullable()->constrained()->cascadeOnDelete();

            // Null is a wild card: "some cards are marked as wild and can be
            // used as any type", so its suit is not missing, it is whichever
            // the set it joins needs.
            $table->string('suit')->nullable();
            $table->unsignedInteger('value');

            $table->string('zone')->default('deck');

            // Order within the deck, written by the shuffle. Cards are drawn
            // from the lowest, so a shuffle is a rewrite of this column rather
            // than a reordering of rows.
            $table->unsignedInteger('position')->default(0);

            // What the card prints beyond its suit and value - "No single" on
            // the cards two of the deck customisation technologies add. The
            // rulebook never defines those words, so they are shown and not
            // acted on: the table reads them, exactly as it reads a Facility
            // type's effect.
            $table->string('restriction')->nullable();

            $table->timestamps();

            $table->index(['game_id', 'corporation_id', 'zone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_cards');
    }
};
