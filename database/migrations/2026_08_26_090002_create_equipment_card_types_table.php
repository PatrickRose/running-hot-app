<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Equipment cards Runners carry into a Run (rulebook 3.4.1).
        //
        // Per game and fully editable, for the same reason the Protection Card
        // catalogue is: Control adds cards during play. Several technologies
        // hand out equipment that is not on the market at all - DTC's
        // "Unfortunate Malfunction" invents a bypass card per protection card -
        // so the list has to grow without a deployment.
        Schema::create('equipment_card_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            // The code printed on the card (EEP002, ERS024). Nullable, because a
            // card Control invents mid-game has none and is shown as its text.
            $table->string('code')->nullable();

            $table->string('name');

            // Permanent, this run, or single use. This is the one attribute the
            // application has to know rather than merely show: it decides when
            // the card may be played, whether it counts against the three
            // equipped items a Runner may carry, and whether it goes back to
            // Control afterwards.
            $table->string('category');

            // What the card does, as printed. Every one of these effects belongs
            // to the Run loop, which is not built, so the words are the whole of
            // it for now.
            $table->text('effect');

            // What the market charges. Null means the market does not sell it:
            // the bypass cards and the reconnaissance items are granted by a
            // technology or a Facility's access effect instead.
            $table->unsignedInteger('cost')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['game_id', 'code']);
            $table->index(['game_id', 'category']);
            $table->index(['game_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_card_types');
    }
};
