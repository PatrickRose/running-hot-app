<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the shop is selling, at what price, and how much of it is left
        // (rulebook 3.3.3, and 2.1 for the Runners' market).
        //
        // A line rather than a column on the card, which is the decision this
        // table exists to record. The card sheet has a cost column and the
        // application deliberately does not seed it: the shop does not price
        // cards the way the sheet suggests, and a wrong price baked into the
        // catalogue would be worse than none because everything downstream
        // would be built on it. So the price is Control's, it is per game, and
        // it is per *line* - the same card can be five Credits on Saturday and
        // twelve on Sunday without either game touching the other.
        Schema::create('shop_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            // A morph, because the two counters sell genuinely different
            // things: a Protection Card bought by a Security player out of the
            // Corporation's Credits, and an Equipment card bought by a Runner
            // out of their own. Neither gets a nullable column it never uses,
            // which is the reasoning council_ballots.voter already follows.
            $table->morphs('stockable');

            // Credits. Unsigned, because a shop that pays you to take a card is
            // Control handing one over - which is the holdings controls, not
            // this. Zero is allowed and is a real answer: a card given away.
            $table->unsignedInteger('price')->default(0);

            // Copies left on the shelf, or null for a line that never runs out.
            // "First come first served" (3.3.3) is the only allocation rule the
            // rulebook gives, so a count is all the shop needs - there is no
            // per-buyer limit here because there is none in the book.
            $table->unsignedInteger('stock')->nullable();

            $table->string('status')->default('on_sale');

            // Control's own words on the line: what a rumoured card is waiting
            // on, who has first refusal, what was agreed at the table. The
            // rulebook has Control announcing the shop, and an announcement is
            // more than a number.
            $table->text('notes')->nullable();

            $table->timestamps();

            // One line per card per game. Two prices for the same card is a
            // question nobody can answer at the counter, and Control changing
            // their mind is an edit rather than a second line.
            $table->unique(['game_id', 'stockable_type', 'stockable_id'], 'shop_listings_card_unique');
            $table->index(['game_id', 'status'], 'shop_listings_game_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_listings');
    }
};
