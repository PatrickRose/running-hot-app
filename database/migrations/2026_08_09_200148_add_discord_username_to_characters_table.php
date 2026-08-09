<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // The Discord handle Control expects to claim this character.
            //
            // This is a claim ticket, not an identity: it is matched once, at
            // the player's first sign in, and then resolved to user_id. Discord
            // handles can be changed at any time, so nothing may depend on this
            // staying accurate after the character has been claimed.
            $table->string('discord_username')->nullable()->after('user_id');

            $table->index(['game_id', 'discord_username']);
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'discord_username']);
            $table->dropColumn('discord_username');
        });
    }
};
