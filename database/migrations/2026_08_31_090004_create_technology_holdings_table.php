<?php

use App\Enums\ResearchSuit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A technology card a Corporation actually has (rulebook 3.2.2).
        //
        // technology_types is the tree - what could be researched. This is what
        // has been: the card flipped over, stored in a Facility, and doing
        // whatever it says. Or not yet doing it: a copy a Runner brought back
        // or one Research Control made for a partner is Claimed rather than
        // Researched, occupies its Facility's storage all the same (3.2.6,
        // footnote 8), and is worth a discount when the Corporation pays for
        // it.
        Schema::create('technology_holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corporation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_type_id')->constrained()->cascadeOnDelete();

            // Where the card is stored. Required for a researched technology -
            // "You must then place this in one of your Facilities" - but
            // nullable, because a Facility can be lost and a claimed copy can
            // be in a Control member's hand for a moment before it is placed.
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status')->default('researched');
            $table->string('origin')->default('researched');

            // Off the research cost, as a percentage: 25 for a weak copy, 50
            // for a good one or a theft. Stored rather than read off the origin
            // because 3.2.6 leaves it to "the strength of the copy" as Research
            // Control judges it.
            $table->unsignedInteger('discount_percent')->default(0);

            // What was actually paid, suit by suit. The cost on the tree can be
            // edited afterwards and a discount is applied at the till, so the
            // price a Corporation paid is only knowable if it is written down.
            foreach (ResearchSuit::all() as $suit) {
                $table->unsignedInteger('paid_'.$suit->value)->default(0);
            }

            $table->timestamp('researched_at')->nullable();
            $table->timestamp('destroyed_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['game_id', 'corporation_id', 'status']);
            $table->index(['facility_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technology_holdings');
    }
};
