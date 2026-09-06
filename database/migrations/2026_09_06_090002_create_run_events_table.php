<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everything that happened during a run, in the order it happened.
        //
        // Append-only, and the same instinct as tracker_adjustments: a run is
        // escalating arithmetic under time pressure with hidden information on
        // both sides, so players will contest the result and "why did that
        // happen?" needs an answer three turns later. Nothing here is ever
        // updated or deleted - a mistake is corrected by Control acting again,
        // which is itself an event.
        //
        // Deliberately not one table per kind of thing. An activation, a boost,
        // a consequence and a Runner walking away are all just "what happened
        // next", and the value of the log is that it reads in one order.
        Schema::create('run_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();

            // Which time round the Activate/Challenge/Consequence/Breather loop
            // this was. Numbered from 1, and what enforces "each Runner may use
            // one card per pass" (3.4.2).
            $table->unsignedInteger('pass');
            $table->string('step');
            $table->string('type');

            // Who did it. Null for something the engine did on its own - the
            // alerts generated at the start of a run belong to nobody. This is
            // the User rather than the Character because the question it
            // answers is "was that the player or was that Control?", and
            // Control has no Character.
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Who it happened to, where that is one person: who took the
            // consequence, who left, who was incapacitated.
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();

            // Which card was being faced. Null for the events between cards.
            $table->foreignId('facility_protection_card_id')->nullable()
                ->constrained('facility_protection_cards')->nullOnDelete();

            // The numbers behind the event - the cost paid, the strength and
            // where each point of it came from, the Wounds and Tags applied.
            // Free-form because what is worth recording differs per type, and
            // because a shape that fitted every event would be mostly nulls.
            $table->json('payload')->nullable();

            // The same thing in a sentence, written when the event is recorded
            // rather than rebuilt from the payload later. Control reads the log
            // during a game to settle an argument, and a row that has to be
            // interpreted is no use at that speed.
            $table->string('description');

            $table->timestamps();

            // The log in order. The id breaks ties, because several events can
            // share a timestamp to the second.
            $table->index(['run_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_events');
    }
};
