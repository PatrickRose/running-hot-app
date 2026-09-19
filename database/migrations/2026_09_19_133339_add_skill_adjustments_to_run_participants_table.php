<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a Runner's Equipment is doing to their skills for this run.
        //
        // On the participant rather than on the character, because Brawn and
        // Hack are Trackers: permanent, ledgered, and argued about three turns
        // later. A Shiv is carried into one Facility and carried out again, so
        // raising the character's own column would leave the ledger claiming a
        // Runner grew stronger and never got weaker.
        //
        // Signed, because a card may cost a Runner a skill as readily as give
        // them one, and nothing in the rules says which.
        //
        // Not the same thing as App\Support\Runs\RollModifiers, which adds
        // dice to one roll. A skill is halved on the way into the pool for
        // every Runner who is not leading (3.4.2), so +1 Brawn is worth a whole
        // die to the Leader and half of one to everybody else - and a card
        // that reads "+1 Brute" means the skill rather than the die.
        Schema::table('run_participants', function (Blueprint $table) {
            $table->integer('brawn_adjustment')->default(0)->after('position');
            $table->integer('hack_adjustment')->default(0)->after('brawn_adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('run_participants', function (Blueprint $table) {
            $table->dropColumn(['brawn_adjustment', 'hack_adjustment']);
        });
    }
};
