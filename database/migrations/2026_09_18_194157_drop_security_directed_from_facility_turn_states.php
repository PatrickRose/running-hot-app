<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Directing Security is not a mechanic any more (rulebook 3.3.5).
 *
 * The rulebook has a Security player place a meeple at one Facility during
 * Setup, and makes that the price of Boosting a card, paying a Charge and
 * choosing to leave one switched off. In play it turned out to be ceremony: a
 * Security player may move where they are directing freely during the Action
 * phase, so the meeple constrained nobody and the only thing it reliably did
 * was stop somebody Boosting until a second person had ticked a box for them.
 *
 * What survives is the budget, which is the decision that was always doing the
 * work: what Credits are on a Facility still decides what Security can switch
 * on, Boost and Charge.
 *
 * Dropping the column rather than leaving it unread, because a flag on Control's
 * panel that looks mechanical and does nothing is worse than no flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_turn_states', function (Blueprint $table) {
            $table->dropColumn('security_directed');
        });
    }

    public function down(): void
    {
        Schema::table('facility_turn_states', function (Blueprint $table) {
            $table->boolean('security_directed')->default(false);
        });
    }
};
