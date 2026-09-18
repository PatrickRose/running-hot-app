<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A seat at the Council for somebody who is not a Corporation.
 *
 * The rulebook's Council is the CEOs' (3.1), and HM Government is not in it.
 * This is Control's ruling rather than a rule off the page: the Government
 * player sits at the Council and votes with a bloc of six.
 *
 * Held as a count on the character rather than as a flag naming HM Government,
 * because the thing being described is "a seat worth this many votes" - so a
 * Press player or an invited Runner Representative can be given one without
 * new code, which is exactly the sort of ruling 3.1.3's own Runner
 * Representative agenda card invites.
 *
 * Not a tracker, deliberately. Political Will is a Corporation's political
 * capital and moves through TrackerService with a ledger behind it; this is the
 * size of a bloc, which Control sets and nothing in the game spends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // Null for everybody who does not sit at the Council, which is
            // almost everybody. A CEO's votes are their Corporation's Political
            // Will and are not recorded here.
            $table->unsignedInteger('council_votes')->nullable()->after('charisma');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('council_votes');
        });
    }
};
