<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every tracker movement is written here so Control can answer "why did
        // that change?" mid-game, and so an upkeep run can be audited or undone.
        Schema::create('tracker_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->morphs('subject');
            $table->string('tracker');

            $table->integer('value_before');
            $table->integer('value_after');
            $table->integer('delta');

            $table->string('reason')->nullable();
            $table->boolean('automated')->default(false);

            $table->timestamps();

            $table->index(['game_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_adjustments');
    }
};
