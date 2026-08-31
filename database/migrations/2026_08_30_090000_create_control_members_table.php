<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who Control is, for one game.
 *
 * The account-wide is_control flag is granted from the console, which is fine
 * for the person who owns the deployment and useless for the four friends
 * helping run tonight's game. This is the roster Control builds instead: a
 * Discord handle per organiser, claimed the same way a character is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            // Null until that handle signs in. The seat is bound to the account
            // from then on, so a Discord rename cannot take Control away from
            // someone in the middle of a game.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // A claim ticket, exactly as it is on a character: matched once, at
            // sign in, and never depended on afterwards.
            $table->string('discord_username')->nullable();

            $table->timestamps();

            $table->index(['game_id', 'discord_username']);
            // One seat per person per game. Both halves are enforced, because a
            // seat may be named by handle or bound to an account, and a second
            // row either way is a duplicate rather than a second organiser.
            $table->unique(['game_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_members');
    }
};
