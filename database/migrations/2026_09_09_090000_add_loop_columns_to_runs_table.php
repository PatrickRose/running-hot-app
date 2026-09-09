<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            // Cards the Runners got past that were actually Active.
            //
            // Two counts rather than one, because rulebook 3.4.2 and 3.4.4 do
            // not ask the same question. The strength bonus is "for each 2
            // Active Protection Cards already passed", and the consolation
            // payment is for "each 3 Protection Cards you managed to get past".
            // A card Security could not afford to activate is one the Runners
            // walked straight past: it counts towards what they got through and
            // makes nothing that follows it harder.
            //
            // The two only ever differ when Security ran out of budget, which
            // is exactly the case worth being able to read back afterwards.
            $table->unsignedInteger('active_cards_passed')->default(0)->after('cards_passed');

            // A Retry consequence taken this pass, waiting on the Breather.
            //
            // "After the Breather step, you move back to the Activate step" -
            // so a Retry is decided during the Consequence step and takes
            // effect one step later, which is a decision the run has to be
            // holding in the meantime. Every other consequence lets the
            // Runners move on: failing a challenge still passes the card, and
            // the consequence is what it cost them.
            $table->boolean('retry_pending')->default(false)->after('active_cards_passed');
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn(['active_cards_passed', 'retry_pending']);
        });
    }
};
