<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a game's background reading lives - a briefing pack, a
        // setting document, a shared folder - linked from every player's
        // sidebar beside the rulebook. A link rather than a stored file,
        // because it is written and kept up to date somewhere else, and it
        // differs per game. Null means there is nothing to link to yet.
        Schema::table('games', function (Blueprint $table) {
            $table->string('background_url', 2048)->nullable()->after('discord_webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('background_url');
        });
    }
};
