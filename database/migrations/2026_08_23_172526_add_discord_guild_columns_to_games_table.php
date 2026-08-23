<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A game can now own a Discord guild, which the application provisions the
 * roles and channels of.
 *
 * The guild is never created by the application: Discord's Create Guild
 * endpoint only works for bots in fewer than ten guilds and produces a server
 * nobody is a member of. Control makes the server by hand, invites the bot, and
 * pastes the snowflake here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Snowflakes are 64-bit and Discord documents them as strings, so
            // they are stored as strings rather than risking integer overflow.
            $table->string('discord_guild_id')->nullable()->after('discord_webhook_url');

            // Where a player who is not yet in the guild is sent. Provisioning
            // fills this in, and Control may override it.
            $table->text('discord_invite_url')->nullable()->after('discord_guild_id');

            // Provisioning is dozens of rate-limited API calls, so it runs on
            // the queue and reports back through these columns. The Control
            // panel already polls, so it sees the progress without a websocket.
            $table->string('discord_provision_status')->default('idle')->after('discord_invite_url');
            $table->text('discord_provision_message')->nullable()->after('discord_provision_status');
            $table->timestamp('discord_provisioned_at')->nullable()->after('discord_provision_message');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'discord_guild_id',
                'discord_invite_url',
                'discord_provision_status',
                'discord_provision_message',
                'discord_provisioned_at',
            ]);
        });
    }
};
