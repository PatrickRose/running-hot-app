<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Split technologies (rulebook 3.2.7).
        //
        // Eighteen of the game's technologies are printed as "Power (Part 1/4)"
        // and are one technology across several cards. The rulebook gives them
        // different rules for their owner and for a thief - the owner works the
        // technology holding any one piece, anyone who took or copied it needs
        // all of them - so the pieces have to be groupable.
        //
        // Read off the printed name rather than invented: the card says which
        // part of how many it is. App\Support\TechnologyBlueprint parses it.
        Schema::table('technology_types', function (Blueprint $table) {
            // The technology all the pieces belong to - "Power" - and null for
            // the great majority of technologies, which are one card.
            $table->string('split_group')->nullable()->after('name');

            $table->unsignedInteger('split_piece')->nullable()->after('split_group');
            $table->unsignedInteger('split_pieces')->nullable()->after('split_piece');
        });
    }

    public function down(): void
    {
        Schema::table('technology_types', function (Blueprint $table) {
            $table->dropColumn(['split_group', 'split_piece', 'split_pieces']);
        });
    }
};
