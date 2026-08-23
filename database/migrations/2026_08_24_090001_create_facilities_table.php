<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corporation_id')->constrained()->cascadeOnDelete();

            // Restricted rather than cascading: deleting a type that Facilities
            // are still built as would silently orphan them, and Control should
            // be told to move them first.
            $table->foreignId('facility_type_id')->constrained()->restrictOnDelete();

            $table->string('name');

            // Facilities take a turn to build (rulebook 3.3.1): a requisition
            // raised during turn N's Setup phase becomes available during turn
            // N+1's. Storing the turn it opens rather than a boolean means the
            // clock moving is all it takes for the Facility to come online, and
            // Control can bring one forward by editing this.
            $table->unsignedInteger('available_from_turn')->default(1);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['corporation_id', 'name']);
            $table->index(['game_id', 'facility_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
