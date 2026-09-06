<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One group of Runners going at one Facility, during one Action phase.
        //
        // Rulebook 3.4.1: the target is chosen in Secret and submitted at the
        // beginning of the phase, and only the Run Leader submits for a group.
        // The secrecy is enforced by the policy and the presenter rather than
        // by leaving the column out - the application has to know the target to
        // order the runs against it.
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            // A run belongs to a turn, because that is the scope of everything
            // it touches: the security budget, whether Security is Directing
            // here, and which cards are already Active all reset with the turn.
            // 3.4.5 makes it sharper - a run not finished before the Action
            // phase ends is unsuccessful, so a run cannot outlive its turn.
            $table->foreignId('turn_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();

            // The Run Leader, who rolls the dice, chooses who takes a
            // consequence and picks the accesses. Nullable because a Leader who
            // leaves at the Breather hands over to somebody else, and the last
            // Runner leaving empties the seat as the run fails.
            $table->foreignId('run_leader_character_id')->nullable()
                ->constrained('characters')->nullOnDelete();

            $table->string('status')->default('submitted');

            // Where this run sits among the runs against the same Facility, and
            // which of 3.4.1's seven tiebreakers put it there. The reason is
            // stored rather than recomputed because the last tiebreaker is a d8
            // roll, so the ordering is not reproducible from the roster alone -
            // and "why did they go first?" is a question Control will be asked.
            $table->unsignedInteger('order_index')->nullable();
            $table->string('order_reason')->nullable();

            // Alerts are a pool for the duration of the run and then gone, so
            // they are not a Tracker: nothing outside this run can see or spend
            // them and there is no ledger to write. Spent is kept beside the
            // balance so the log can show how much Security got out of them.
            $table->unsignedInteger('alerts')->default(0);
            $table->unsignedInteger('alerts_spent')->default(0);

            // Active Protection Cards the Runners have got past. Drives both
            // the +1-per-2-passed strength bonus of 3.4.2 and the consolation
            // payment of 3.4.4, which is why it is counted rather than derived
            // from the event log.
            $table->unsignedInteger('cards_passed')->default(0);

            // How many "End the Run" consequences have been shrugged off. Each
            // one costs 1 Wound, 1 Tag and 1 Alert *per* instance ignored
            // including itself, so the count is the multiplier and not just a
            // flag (3.4.2).
            $table->unsignedInteger('ignored_end_the_run')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['game_id', 'turn_id']);

            // The queue at one Facility, in the order the groups go in.
            $table->index(['facility_id', 'turn_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
