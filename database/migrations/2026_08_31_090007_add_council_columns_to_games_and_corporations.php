<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two things about the Council that belong to the game rather than to one
 * of its sittings: how long the Council sits before recess, and the order the
 * Chair rotates in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Five minutes into the Setup phase (rulebook 3.1.1), and a column
            // rather than a constant for the same reason the phase durations
            // are: Control runs the game to the clock it wants on the night.
            $table->unsignedInteger('council_recess_seconds')->default(300)->after('team_time_seconds');
        });

        Schema::table('corporations', function (Blueprint $table) {
            // The order the Chair rotates in, which Council Control announces
            // on the day (3.1.1 footnote). Held rather than derived: there is
            // no rule that says whose turn it is, so nothing may guess.
            //
            // Null means "not placed in the rotation yet", which sorts last
            // behind everything Control has ordered.
            $table->unsignedInteger('council_chair_order')->nullable()->after('political_will');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('council_recess_seconds');
        });

        Schema::table('corporations', function (Blueprint $table) {
            $table->dropColumn('council_chair_order');
        });
    }
};
