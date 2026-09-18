<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every die the application has rolled, and what it landed on.
        //
        // Rolls are server-side because a browser that rolls its own dice is a
        // browser that can decide it won, and they are kept because "why did I
        // lose that check?" is the question this table exists to answer. A
        // player who can see six d8s that came up 1,2,2,3,4,4 will accept the
        // result; a player told only "you failed" will not.
        //
        // Its own table rather than a payload on the event, because these are
        // the rows anyone will actually want to read back, and because the
        // faces are the evidence rather than a detail of it.
        Schema::create('run_dice_rolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();

            // The event this roll decided. Nullable so a roll can be written
            // before the event that describes its outcome exists - the d8 that
            // breaks a tie in run ordering (3.4.1) belongs to no step at all.
            $table->foreignId('run_event_id')->nullable()->constrained()->nullOnDelete();

            // Which side rolled. The Runners' pool is one roll for the whole
            // group even though it is assembled from several people's skills,
            // so this is not a character_id on its own.
            $table->string('roller');
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('pool');

            // 6 or 8. A Runner with at least one Wound rolls d6s (3.4.2), and
            // Security always rolls d8s. Stored rather than inferred, because
            // the Wound that changed it may have healed by the time anyone
            // reads this back.
            $table->unsignedInteger('die_faces');

            // 5 or higher succeeds. A column rather than a constant because
            // Control overriding a threshold is exactly the kind of ruling the
            // rulebook keeps deferring to them, and a roll recorded under a
            // different threshold must still explain itself.
            $table->unsignedInteger('threshold')->default(5);

            // Every face, in the order rolled.
            $table->json('faces');
            $table->unsignedInteger('successes');

            // What was being rolled for, in words, so a roll read on its own
            // still says what it decided.
            $table->string('reason');

            $table->timestamps();

            $table->index(['run_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_dice_rolls');
    }
};
