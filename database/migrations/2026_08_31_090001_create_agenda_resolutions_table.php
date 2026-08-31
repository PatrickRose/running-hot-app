<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The options an agenda card offers (rulebook 3.1.4).
 *
 * A row rather than a string in a JSON column, because a ballot points at one:
 * a CEO splits their Political Will between the resolutions on the card, and an
 * allocation has to keep meaning the same thing after the Chair has amended the
 * list.
 *
 * That is also why removal is soft. A resolution that has been voted on and
 * then removed still has to explain the ballots that named it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_card_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('position');
            $table->text('text');

            // An amendment the Chair has proposed and Council Control has not
            // signed off yet (3.1.4). Until it is signed off the card still
            // reads and votes as it stands: an addition is not yet an option, a
            // removal is still one, and a rewording still shows the old words.
            // See App\Enums\ResolutionAmendment.
            $table->string('pending_amendment')->nullable();
            $table->text('pending_text')->nullable();

            $table->foreignId('proposed_by_character_id')->nullable()
                ->constrained('characters')->nullOnDelete();

            // Soft, so ballots that named this resolution still read back.
            $table->timestamp('removed_at')->nullable();

            $table->timestamps();

            $table->index(['agenda_card_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_resolutions');
    }
};
