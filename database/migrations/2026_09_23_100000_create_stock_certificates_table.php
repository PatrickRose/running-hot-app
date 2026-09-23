<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A share in a Corporation's Income, taken out of one of its Corporate
        // Facilities (rulebook 3.4.3) and handed to a Runner by Control.
        //
        // Deliberately not an Equipment card: it is not played on a run and
        // nothing about it is printed on a sheet. What it does - pay out a
        // share of the issuing Corporation's Income - is a rule the
        // application applies, so it is a row of its own.
        Schema::create('stock_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            // Whose Income it is a share of. A certificate outlives nothing of
            // its Corporation: with the Corporation gone there is no Income to
            // take a share of.
            $table->foreignId('corporation_id')->constrained()->cascadeOnDelete();

            // Who is holding it now. Nullable on delete so a certificate
            // survives a seat Control removes; Control hands it on from there.
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();

            // How it was cashed, and what that paid, kept on the row rather
            // than read back: the Income it was a share of moves every turn.
            $table->string('cashed_as')->nullable();
            $table->unsignedInteger('credits_paid')->nullable();
            $table->timestamp('cashed_at')->nullable();
            $table->foreignId('cashed_by_character_id')->nullable()->constrained('characters')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_certificates');
    }
};
