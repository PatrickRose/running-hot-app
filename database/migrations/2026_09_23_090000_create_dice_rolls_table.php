<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A roll a player made for Control to read.
        //
        // Not a run's roll: those are `run_dice_rolls`, hang off a run and
        // carry a threshold the run decided. These are everything else the
        // game asks somebody to roll for - a Freelancer's special rule, a
        // ruling Control wants the dice to settle - and the only rule about
        // them is the game's own, that a 5 or better is a success.
        Schema::create('dice_rolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            // Who pressed the button, and which seat they were rolling as.
            // Both nullable on delete so a roll outlives an account or a seat
            // Control removes: what the dice said is the point of keeping it.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('d6');
            $table->unsignedSmallInteger('d8');

            // Every face, kept per die size, because "which of those was the
            // 8?" is the question somebody asks afterwards.
            $table->json('faces');
            $table->unsignedSmallInteger('successes');

            // What the roll was for, in the player's words.
            $table->string('purpose')->nullable();

            $table->timestamps();

            $table->index(['game_id', 'created_at'], 'dice_rolls_game_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dice_rolls');
    }
};
