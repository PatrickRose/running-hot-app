<?php

use App\Enums\ResearchSuit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Research Points, banked per Corporation and per suit (rulebook
        // 3.2.1). They sit on the Corporation rather than on its Research
        // player because 3.2.5 trades them "between different Corporations",
        // and because a Corporation with two Research players still has one
        // pile of tokens.
        //
        // Four columns rather than a table of totals, so they are Trackers like
        // Credits and Political Will: every movement then lands in
        // tracker_adjustments with a reason against it, which is how Control
        // answers "why does Gordon have eleven Cog?" three turns later.
        Schema::table('corporations', function (Blueprint $table) {
            foreach (ResearchSuit::all() as $suit) {
                $table->unsignedInteger($suit->pointsColumn())->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporations', function (Blueprint $table) {
            $table->dropColumn(array_map(
                fn (ResearchSuit $suit): string => $suit->pointsColumn(),
                ResearchSuit::all(),
            ));
        });
    }
};
