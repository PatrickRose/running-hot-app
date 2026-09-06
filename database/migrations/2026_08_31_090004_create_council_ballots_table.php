<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Corporation's vote on one agenda item, as handed to the Chair (3.1.2).
 *
 * The ballot is the unit rather than the allocation, because submitting is one
 * act: a CEO writes the whole slip and hands it over, and the Chair hands the
 * whole slip back when they declare the vote secret after it arrived.
 *
 * A returned ballot is kept rather than deleted. The Chair knowing that a
 * Corporation had already voted before secrecy was declared is exactly the sort
 * of thing the Chair is allowed to know, and a deleted row could not say it.
 * "One live ballot per Corporation per item" is therefore enforced in
 * App\Services\CouncilService rather than by a unique index, which could not
 * express it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_ballots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_agenda_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corporation_id')->constrained()->cascadeOnDelete();

            // Which CEO handed it over, and as which account. Both nullable
            // because Control may submit on behalf of a player who is at the
            // table rather than at a laptop.
            $table->foreignId('character_id')->nullable()
                ->constrained('characters')->nullOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('submitted_at');

            // Set when the Chair hands the slip back, which is what the
            // rulebook requires of votes already in when a vote is declared
            // secret. A returned ballot counts for nothing in the tally.
            $table->timestamp('returned_at')->nullable();
            $table->string('returned_reason')->nullable();

            $table->timestamps();

            $table->index(['council_agenda_item_id', 'corporation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_ballots');
    }
};
