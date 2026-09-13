<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An equation somebody played, and what it paid (rulebook 3.2.1).
        //
        // A row of its own because the rulebook asks for one: "Scoring can and
        // should be done while other players are taking their turns". Playing
        // and scoring are therefore two acts - the cards are spent and the turn
        // passes immediately, and the arithmetic waits in Pending until its
        // player gets to it. Nothing at the table blocks on a player doing
        // sums.
        //
        // The cards are stored as they were played rather than as foreign keys.
        // They are spent the moment the equation lands, and a spent card can be
        // gathered back into its deck when the next session opens - so a link
        // would stop meaning what it said. What Control needs to see three
        // turns later is the equation, not where its cards ended up.
        Schema::create('research_equations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('turn_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('corporation_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('pending');

            // Each side as it was played: suit, value and whether the card came
            // out of the hand or off the public pool.
            $table->json('left_cards');
            $table->json('right_cards');

            // Derived from the cards, and stored because they are what the row
            // is read for: a Control panel listing forty equations should not
            // be re-deriving the arithmetic of each one to show its totals.
            $table->unsignedInteger('cards_per_side');
            $table->unsignedInteger('left_sum');
            $table->unsignedInteger('right_sum');
            $table->boolean('balanced')->default(false);
            $table->unsignedInteger('bonus')->default(0);

            // The payout, once somebody has taken it: which side was scored,
            // in which suit, and the points by suit that actually moved. The
            // last of those is what the balanced bonus split ends up as.
            $table->string('scored_side')->nullable();
            $table->string('scored_suit')->nullable();
            $table->json('awards')->nullable();
            $table->foreignId('scored_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scored_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_equations');
    }
};
