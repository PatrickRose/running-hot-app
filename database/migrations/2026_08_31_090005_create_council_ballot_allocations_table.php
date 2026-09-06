<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much Political Will a ballot puts behind each resolution.
 *
 * A CEO may split their Political Will across the resolutions on the card, so a
 * ballot is a set of these rather than a single choice. What a Corporation may
 * spread over one card is capped by the Political Will it holds - the vote is
 * weighted by it, and it is never spent: the tracker moves only when Control
 * moves it, which is the one thing the rulebook does say Political Will is for
 * at the Council (an absence costs some, 3.1.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_ballot_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_ballot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_resolution_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('political_will');

            $table->timestamps();

            $table->unique(['council_ballot_id', 'agenda_resolution_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_ballot_allocations');
    }
};
