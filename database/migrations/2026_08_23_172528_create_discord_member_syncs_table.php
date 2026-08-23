<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The outcome of the last attempt to push a player's roles into a game's guild.
 *
 * Recorded rather than recomputed because the interesting answer — "who has not
 * joined the Discord server yet?" — is one Control wants on a screen without
 * every page load fanning out to the Discord API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_member_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('status');
            $table->text('message')->nullable();

            // The application-managed roles the player ended up holding, kept
            // for the audit trail Control expects of anything automated.
            $table->json('role_ids')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_member_syncs');
    }
};
