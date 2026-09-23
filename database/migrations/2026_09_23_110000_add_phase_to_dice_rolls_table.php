<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The phase a roll was made in, as the tracker ledger records one:
        // "which turn was that?" is the question Control asks about a roll it
        // is ruling on after the fact. Null for a roll made with no phase
        // running - before the first Setup, or after the game has finished.
        Schema::table('dice_rolls', function (Blueprint $table) {
            $table->foreignId('phase_id')->nullable()->after('character_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dice_rolls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('phase_id');
        });
    }
};
