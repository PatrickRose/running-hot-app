<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('sequence');
            $table->string('status')->default('pending');

            // The clock is server authoritative: ends_at is mutated directly by
            // extensions and by resuming from a pause, so remaining time is
            // always ends_at minus now (or minus paused_at while paused).
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            // Bumped on every clock mutation so a stale delayed job no-ops.
            $table->unsignedInteger('version')->default(0);

            // Guards the Team Time upkeep against running twice.
            $table->timestamp('upkeep_applied_at')->nullable();

            $table->timestamps();

            $table->unique(['turn_id', 'sequence']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phases');
    }
};
