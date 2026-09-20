<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Not every Chair is a Corporation.
 *
 * The Chair was bound to one, on the reading that 3.1 seats the CEOs and the
 * rotation runs between them. It is the designer's ruling that anybody with a
 * seat may take it - which matters at the first turn of every game, because the
 * game opens with HM Government in the Chair.
 *
 * So the Chair becomes a morph, exactly as `council_ballots.voter` already did
 * and for the same reason: a CEO chairs for their Corporation and a seated
 * character chairs as itself, and neither should get a nullable column the
 * other never uses.
 *
 * `characters.council_chair_order` is the other half. A Corporation is in the
 * rotation because it is a Corporation; a seated character is in it only when
 * Control has put them there, which is what makes the rotation something
 * Council Control announces (3.1.1) rather than something derived from who
 * happens to hold a seat.
 *
 * Every existing sitting's Chair is a Corporation, so the backfill is exact and
 * a game mid-session keeps whoever is in the Chair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('council_sessions', function (Blueprint $table) {
            // Nullable to begin with, because a vacant Chair is a real state
            // and the rows that already exist have nothing here until the
            // backfill below.
            $table->nullableMorphs('chair');
        });

        DB::table('council_sessions')
            ->whereNotNull('chair_corporation_id')
            ->update([
                'chair_type' => 'corporation',
                'chair_id' => DB::raw('chair_corporation_id'),
            ]);

        Schema::table('council_sessions', function (Blueprint $table) {
            $table->dropForeign(['chair_corporation_id']);
            $table->dropColumn('chair_corporation_id');
        });

        Schema::table('characters', function (Blueprint $table) {
            // Null for almost everybody, which is the point: a seat is in the
            // rotation only when Control has ordered it.
            $table->unsignedInteger('council_chair_order')->nullable()->after('council_votes');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('council_chair_order');
        });

        Schema::table('council_sessions', function (Blueprint $table) {
            $table->foreignId('chair_corporation_id')->nullable()
                ->constrained('corporations')->nullOnDelete();
        });

        // Only a Corporation can go back into that column. A sitting a
        // character was chairing loses its Chair rather than being given to
        // somebody who was not in it.
        DB::table('council_sessions')
            ->where('chair_type', 'corporation')
            ->update(['chair_corporation_id' => DB::raw('chair_id')]);

        Schema::table('council_sessions', function (Blueprint $table) {
            $table->dropMorphs('chair');
        });
    }
};
