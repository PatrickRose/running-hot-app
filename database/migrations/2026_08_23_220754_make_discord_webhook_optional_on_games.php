<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A game no longer has to be given an announcement webhook up front.
 *
 * Requiring one made sense when Control created every webhook by hand, but
 * provisioning a game's Discord server now makes the webhook itself — so
 * demanding one at creation asked Control for the very thing the application
 * was about to produce. The only way through was to invent a URL and let
 * provisioning overwrite it, which is what the placeholder below was for.
 *
 * This is not a return to the global DISCORD_WEBHOOK_URL that
 * require_discord_webhook_on_games removed: there is still no default to post
 * to by mistake. A game without a webhook simply has nowhere to announce yet,
 * and announcements skip rather than fall back.
 */
return new class extends Migration
{
    /** The stand-in the previous migration and the seeder wrote. */
    private const PLACEHOLDER = 'https://discord.com/api/webhooks/000000000000000000/replace-me';

    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->text('discord_webhook_url')->nullable()->change();
        });

        // Now that null is allowed, the placeholder has nothing left to stand in
        // for. Leaving it would keep those games looking configured while every
        // announcement failed against an endpoint that does not exist.
        DB::table('games')
            ->where('discord_webhook_url', self::PLACEHOLDER)
            ->update(['discord_webhook_url' => null]);
    }

    public function down(): void
    {
        DB::table('games')
            ->whereNull('discord_webhook_url')
            ->update(['discord_webhook_url' => self::PLACEHOLDER]);

        Schema::table('games', function (Blueprint $table) {
            $table->text('discord_webhook_url')->nullable(false)->change();
        });
    }
};
