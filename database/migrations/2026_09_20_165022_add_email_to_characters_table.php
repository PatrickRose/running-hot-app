<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second claim ticket, beside the Discord handle.
 *
 * A handle is what Control has when the roster is built from the sign-up list,
 * and it is wrong often enough to matter: people mistype it, people rename
 * themselves between signing up and turning up, and a handle Control never got
 * leaves a player with no way in at all. The email they signed up with is the
 * other thing Control always has, so it is the fallback - see
 * App\Actions\ClaimCharactersByEmail.
 *
 * Nullable, because most characters are claimed by handle and never need it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('discord_username');

            // Claims are looked up per game by email, the way they already are
            // by handle.
            $table->index(['game_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table): void {
            $table->dropIndex(['game_id', 'email']);
            $table->dropColumn('email');
        });
    }
};
