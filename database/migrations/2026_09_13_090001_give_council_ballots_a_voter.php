<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Not every seat at the Council is a Corporation.
 *
 * A ballot was bound to one, which was true right up until HM Government was
 * seated with a bloc of five (Control's ruling; rulebook 3.1 seats only the
 * CEOs). A CEO votes for their Corporation and the Government votes for itself,
 * so the voter becomes a morph rather than either of them getting a nullable
 * column the other never uses.
 *
 * Every existing ballot is a Corporation's, so the backfill is exact and
 * nothing is lost: this runs on a game mid-session without disturbing a vote
 * already with the Chair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('council_ballots', function (Blueprint $table) {
            // Nullable to begin with, because the rows that already exist have
            // nothing to put here until the backfill below.
            $table->nullableMorphs('voter');
        });

        DB::table('council_ballots')->update([
            'voter_type' => 'corporation',
            'voter_id' => DB::raw('corporation_id'),
        ]);

        Schema::table('council_ballots', function (Blueprint $table) {
            $table->dropForeign(['corporation_id']);

            // The replacement index goes in before the old one comes out, not
            // after. council_agenda_item_id carries a foreign key of its own and
            // the composite index is what backs it, so MySQL refuses to drop it
            // until another index leads with that column. SQLite has no such
            // rule, which is why the order looked arbitrary.
            $table->index(['council_agenda_item_id', 'voter_type', 'voter_id']);

            $table->dropIndex(['council_agenda_item_id', 'corporation_id']);
            $table->dropColumn('corporation_id');
        });
    }

    public function down(): void
    {
        Schema::table('council_ballots', function (Blueprint $table) {
            $table->foreignId('corporation_id')->nullable()->constrained()->cascadeOnDelete();
        });

        // Only a Corporation's ballot can go back, which is every ballot there
        // was before this migration and not the Government's.
        DB::table('council_ballots')
            ->where('voter_type', 'corporation')
            ->update(['corporation_id' => DB::raw('voter_id')]);

        DB::table('council_ballots')->whereNull('corporation_id')->delete();

        Schema::table('council_ballots', function (Blueprint $table) {
            // Same ordering as up(), for the same reason.
            $table->index(['council_agenda_item_id', 'corporation_id']);

            $table->dropIndex(['council_agenda_item_id', 'voter_type', 'voter_id']);
            $table->dropMorphs('voter');
        });
    }
};
