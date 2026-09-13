<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A technology a Corporation already has when the game opens
        // (rulebook 3.2.2).
        //
        // Twenty of them, and they are on the tree so their split pieces can be
        // tracked rather than because anybody pays for them - which is why they
        // cost nothing in every suit. Cost is not the marker, though: fourteen
        // other technologies are free without being anybody's starting position,
        // the six deck customisation rows among them.
        //
        // Nor is the description, even though seventeen of the twenty carry the
        // words "Starting tech" in it. Gordon's three are plot hooks with real
        // descriptions, so reading the marker off that column would have handed
        // Gordon nothing - which is exactly what it did until somebody noticed.
        //
        // A column rather than a list in code, so Control can mark one on a
        // Corporation they invented mid-game.
        Schema::table('technology_types', function (Blueprint $table) {
            $table->boolean('starting')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('technology_types', function (Blueprint $table) {
            $table->dropColumn('starting');
        });
    }
};
