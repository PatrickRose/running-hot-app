<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many copies of a card a Corporation owns.
        //
        // The briefings give each Corporation counts - "4 copies of Security
        // Team" - and the count matters because of the one-copy-per-Facility
        // rule of rulebook 3.3.4: four copies means that card can defend at
        // most four Facilities at once. Without this the same card could be
        // installed everywhere at no cost.
        //
        // Held per Corporation rather than per Security player, because the
        // cards are the Corporation's assets (2.3.1) and its Security players
        // may trade them between themselves at the table.
        Schema::create('protection_card_holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protection_card_type_id')->constrained()->cascadeOnDelete();

            // Copies in hand, waiting to be installed. A copy that is installed
            // is not counted here: it is the row in facility_protection_cards.
            // So a Corporation's total copies of a card is this plus however
            // many of its Facilities have one, and installing moves a copy from
            // one to the other.
            $table->unsignedInteger('copies')->default(0);

            $table->timestamps();

            $table->unique(['corporation_id', 'protection_card_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protection_card_holdings');
    }
};
