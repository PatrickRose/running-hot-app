<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_id')->nullable()->unique()->after('is_control');
            $table->string('discord_username')->nullable()->after('discord_id');
            $table->string('discord_avatar')->nullable()->after('discord_username');
        });

        // Players who only ever sign in with Discord never set a password.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['discord_id', 'discord_username', 'discord_avatar']);
        });
    }
};
