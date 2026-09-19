<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What has left the shop, and who took it.
        //
        // The stock count on a listing is a running total, and a running total
        // nobody can reconcile is a number Control ends up arguing with. This
        // is the same instinct as `tracker_adjustments`: the count says where
        // the shelf ended up, and these rows say how it got there.
        //
        // It is not a duplicate of the tracker ledger, which records the
        // Credits. A card given away at nought Credits moves no tracker and so
        // leaves no ledger row at all, and "first come first served" disputes
        // are settled on the clock rather than on the money.
        Schema::create('shop_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_listing_id')->constrained()->cascadeOnDelete();

            // The phase it happened in, as the tracker ledger records one: the
            // shop opens during Setup (3.3.3), so "which Setup" is the question
            // that gets asked about a card somebody says they never bought.
            $table->foreignId('phase_id')->nullable()->constrained()->nullOnDelete();

            // Who stood at the counter. Always somebody, because a purchase is
            // an act rather than a delivery - Control buying on a player's
            // behalf still names the player it is for.
            $table->foreignId('character_id')->nullable()->constrained()->nullOnDelete();

            // Whose money, for a Protection Card. The Corporation pays and the
            // Corporation ends up holding the card, and its Security player is
            // the character above - two different answers to "who bought it",
            // and "who spent our Credits?" wants both of them.
            $table->foreignId('corporation_id')->nullable()->constrained()->nullOnDelete();

            // What it actually cost, rather than what the line says now. A
            // price Control raised next turn must not rewrite what was paid
            // last turn, and a refund has to hand back the same number.
            $table->unsignedInteger('price_paid');

            $table->timestamps();

            $table->index(['game_id', 'created_at'], 'shop_purchases_game_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_purchases');
    }
};
