<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the application has created inside a game's Discord guild.
 *
 * The `key` is the application's own stable name for a thing ("role:control",
 * "channel:gang:7:text"), and the row maps it to the snowflake Discord gave
 * back. Reconciling is then a lookup rather than a name search, so renaming a
 * corporation in the Control panel renames its role instead of creating a
 * second one, and a role somebody deleted by hand is noticed and rebuilt.
 *
 * Only rows recorded here are ever touched. Anything else in the guild belongs
 * to Control and is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            $table->string('kind');
            $table->string('key');
            $table->string('discord_id');

            // What the resource was last named, so drift can be detected
            // without asking Discord for every object on every reconcile.
            $table->string('name');

            $table->timestamps();

            $table->unique(['game_id', 'key']);
            $table->index(['game_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_resources');
    }
};
