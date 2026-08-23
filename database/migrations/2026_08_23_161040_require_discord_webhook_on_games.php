<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every game announces to its own Discord channel, so a webhook is now required
 * rather than optional with a global DISCORD_WEBHOOK_URL fallback.
 *
 * A global default is a footgun: a game created for testing quietly posts to
 * whatever channel the environment happens to point at. Note that this
 * deliberately does not copy the old global value onto existing games, because
 * doing so silently would reproduce exactly the behaviour being removed.
 * Control sets each game's channel explicitly instead.
 */
return new class extends Migration
{
    /**
     * Stand-in for rows predating the requirement. Deliberately not a real
     * endpoint: posting to it fails and is logged, and the Control panel flags
     * it so it is visibly waiting to be replaced.
     */
    private const PLACEHOLDER = 'https://discord.com/api/webhooks/000000000000000000/replace-me';

    public function up(): void
    {
        DB::table('games')
            ->whereNull('discord_webhook_url')
            ->update(['discord_webhook_url' => self::PLACEHOLDER]);

        Schema::table('games', function (Blueprint $table) {
            $table->text('discord_webhook_url')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->text('discord_webhook_url')->nullable()->change();
        });

        DB::table('games')
            ->where('discord_webhook_url', self::PLACEHOLDER)
            ->update(['discord_webhook_url' => null]);
    }
};
