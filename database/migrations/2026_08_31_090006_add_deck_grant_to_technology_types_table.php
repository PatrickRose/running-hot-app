<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deck customisation, priced on the tree (rulebook 3.2.3).
        //
        // "The costs for this are denoted in your tech tree, and should be
        // handled as if you are researching any other technology" - so the six
        // "Research deck" rows on the common tree are the price list, and this
        // is what they grant, structured enough to act on.
        //
        // It cannot go in the four cost columns, because these six do not price
        // like anything else on the tree: "spend 4 research credits in any
        // suit", "6 in any suit and 3 in another", "5 from each suit". The suits
        // are the player's choice, which is the whole point of customising a
        // deck. So the cost is a list of amounts the player assigns to suits,
        // and the grant says what card comes out.
        //
        // One json column rather than seven of them: this describes six rows out
        // of a hundred and forty-four, Control may write more (3.2.4), and the
        // shape is the printed text rather than a schema anyone else reads.
        Schema::table('technology_types', function (Blueprint $table) {
            $table->json('deck_grant')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('technology_types', function (Blueprint $table) {
            $table->dropColumn('deck_grant');
        });
    }
};
