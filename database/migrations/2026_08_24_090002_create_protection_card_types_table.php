<?php

use App\Enums\ProtectionCardAvailability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The card catalogue (rulebook 3.3.2 and 3.3.3). Per game and fully
        // editable, because the rulebook says outright that other Protection
        // Cards exist and only become available after certain game conditions
        // have passed - which is Control's judgement, not a rule the
        // application can evaluate.
        Schema::create('protection_card_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('kind');

            $table->unsignedInteger('cost')->default(0);

            // The challenge: which skill the Runner rolls, and how many d8s
            // Security rolls against them.
            $table->string('challenge_skill');
            $table->unsignedInteger('challenge_strength')->default(1);

            $table->text('consequence');

            // Charge is optional, and only usable where Security is Directing
            // Security (rulebook 3.3.5). Both columns move together: a card
            // either has a Charge with a cost, or has neither.
            $table->unsignedInteger('charge_cost')->nullable();
            $table->text('charge_consequence')->nullable();

            $table->string('availability')->default(ProtectionCardAvailability::Available->value);
            $table->text('notes')->nullable();

            $table->timestamps();

            // One catalogue entry per title: the one-copy-per-Facility rule is
            // enforced by title, so two entries sharing one would be ambiguous.
            $table->unique(['game_id', 'name']);
            $table->index(['game_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protection_card_types');
    }
};
