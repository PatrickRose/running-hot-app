<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Facility with no Corporation is a Plot Facility: one Control builds for
     * the Runners to hit, belonging to nobody in the game's roster.
     *
     * Nullable rather than a second table, because everything a Run operates on
     * - the ordered stacks, the four steps, the accesses, the Discord channels
     * it happens in - already hangs off a Facility, and a parallel model would
     * be the whole Run loop written twice.
     *
     * The delete rule is left cascading on purpose. A Corporation removed from
     * the roster still takes its Facilities with it: turning them into Plot
     * Facilities instead would quietly hand Control a building it never built.
     */
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->foreignId('corporation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->foreignId('corporation_id')->nullable(false)->change();
        });
    }
};
