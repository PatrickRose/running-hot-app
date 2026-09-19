<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a Runner brought on a run, and what they have played during it.
        //
        // One table for both, because the rulebook's three categories differ in
        // *when* a card is used rather than in what a used card is (3.4.1):
        //
        // - A Permanent item is equipped before the run begins, "by placing
        //   them in front of you", so its row is written when the run is
        //   submitted and carries no pass or step.
        // - A This-run or Single-use card is played "as you encounter
        //   Protection Cards", so its row is written then and records which
        //   pass and step it was played in.
        //
        // That last pair is what enforces the cap in 3.4.2: "Each Runner in the
        // Runner group may use one card during these steps", which the worked
        // examples make per Runner per step rather than per run. Counting rows
        // for one (run, character, pass, step) is the whole of it, so nothing
        // has to be stored alongside and kept in step.
        Schema::create('run_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_card_type_id')->constrained()->cascadeOnDelete();

            // Null for a Permanent item, which was equipped before the run
            // rather than played during it.
            $table->unsignedInteger('pass')->nullable();
            $table->string('step')->nullable();

            $table->timestamps();

            $table->index(['run_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_equipment');
    }
};
