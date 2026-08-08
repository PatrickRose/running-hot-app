<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('draft');

            // Global trackers (rulebook 2.3.3). Both are Control-driven values.
            $table->integer('stability')->default(6);
            $table->integer('civil_unrest')->default(0);

            // Phase durations in seconds, defaulting to the rulebook's 15/15/5.
            $table->unsignedInteger('setup_seconds')->default(900);
            $table->unsignedInteger('action_seconds')->default(900);
            $table->unsignedInteger('team_time_seconds')->default(300);

            // When false, Control must advance every phase by hand.
            $table->boolean('auto_advance')->default(true);

            $table->text('discord_webhook_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
