<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who is on a run, and who is still on it.
        //
        // A group is not a gang: rulebook 3.4 has Runners banding together for
        // a run and splitting the rewards, and nothing says they share a gang.
        // So the group is this table rather than a gang_id on the run.
        Schema::create('run_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();

            // The order the Breather goes round in. 3.4.2 has each Runner
            // "starting with the Run Leader" choose whether to leave, so the
            // question is asked in a fixed order rather than all at once.
            $table->unsignedInteger('position');

            // Null while they are still in. Set when they walk away at a
            // Breather or when Wounds reach Body - which are different things,
            // hence the reason: leaving may cost the gang Notoriety and being
            // incapacitated explicitly does not (3.4.2).
            $table->timestamp('left_at')->nullable();
            $table->string('left_reason')->nullable();

            $table->timestamps();

            // A Runner is on a run once. Joining late is not a thing the
            // rulebook has: the group is fixed when the target is submitted.
            $table->unique(['run_id', 'character_id']);
            $table->index(['run_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_participants');
    }
};
