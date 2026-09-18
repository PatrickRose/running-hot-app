<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many copies of an Equipment card a Runner is carrying.
        //
        // Held per Character rather than per gang, which is what the rulebook
        // says twice: 3.4.1 caps *you* at three equipped permanent items and
        // one copy of each card by title, and 3.4.2 hands *your* permanent
        // Equipment to the Security player when you are incapacitated. Neither
        // sentence means anything about a shared pile.
        //
        // Control sets the count outright, exactly as it does for Protection
        // Cards: the market, trades between Runners and everything else in
        // 2.2.1 happens at the table, so the application records where a count
        // ended up rather than replaying how it got there.
        Schema::create('equipment_holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_card_type_id')->constrained()->cascadeOnDelete();

            // Copies in hand. A This-run or Single-use card played on a run is
            // spent out of this, because both are "returned to Control"
            // afterwards (3.4.1); a Permanent one is not, because it comes home
            // with its owner unless they are carried out.
            $table->unsignedInteger('copies')->default(0);

            $table->timestamps();

            $table->unique(['character_id', 'equipment_card_type_id'], 'equipment_holdings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_holdings');
    }
};
