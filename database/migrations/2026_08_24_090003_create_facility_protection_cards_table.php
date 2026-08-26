<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A card installed in a Facility, and where it sits in that Facility's
        // stack (rulebook 3.3.4).
        Schema::create('facility_protection_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protection_card_type_id')->constrained()->cascadeOnDelete();

            // Denormalised from the card type so the two stacks can be indexed
            // and ordered separately. Only ever written from the card type, and
            // a card type's kind is not editable once copies are installed.
            $table->string('kind');

            // 1 is the card Runners meet first. Positions are kept dense within
            // a (facility, kind) stack by the service that writes them, so the
            // stack is always 1..n with no gaps.
            //
            // Not unique: installing renumbers a whole stack in one pass, and a
            // unique index would reject the intermediate states of that pass.
            $table->unsignedInteger('position');

            $table->timestamps();

            // Only one copy of each card title per Facility (rulebook 3.3.4).
            $table->unique(['facility_id', 'protection_card_type_id']);
            $table->index(['facility_id', 'kind', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_protection_cards');
    }
};
