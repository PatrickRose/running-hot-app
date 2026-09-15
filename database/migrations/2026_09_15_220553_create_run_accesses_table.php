<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the Runners took out of a Facility they broke into (rulebook 3.4.3).
 *
 * One row per access spent. Every Runner in the group gets one, and the row
 * says what they spent it on and how it went - so the four kinds share a table
 * and most columns are null for most of them. That is deliberate: an access is
 * one thing a player does with one choice, and splitting it four ways would
 * make "has this Runner used their access?" a query across four tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();

            /** credits, facility_effect, technology or plot. */
            $table->string('kind');

            /**
             * The card drawn, for a technology access. Null for the other
             * three, and null for a technology access against a Facility that
             * turned out to be storing nothing.
             */
            $table->foreignId('technology_holding_id')->nullable()->constrained()->nullOnDelete();

            /** copy, steal or destroy - chosen after the card is revealed. */
            $table->string('action')->nullable();

            /** How the dice went, and which printed band they reached. */
            $table->unsignedSmallInteger('successes')->nullable();
            $table->string('outcome')->nullable();

            /**
             * What a copy or a theft is worth as a research discount, so the
             * number that ends up on a technology_holdings row is the one the
             * dice actually bought rather than one read off the origin later.
             */
            $table->unsignedTinyInteger('discount_percent')->nullable();

            /** Credits paid out by the Credits card (3.4.3). */
            $table->unsignedInteger('credits')->nullable();

            /** Control's, for a plot access and for any ruling on the rest. */
            $table->text('notes')->nullable();

            $table->timestamps();

            // Every question asked of this table is "what has happened on this
            // run", and the commonest is "has this Runner spent theirs".
            $table->index(['run_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_accesses');
    }
};
