<?php

use App\Enums\ResearchCardMarking;
use App\Support\CardMarking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A card carries a list of markings, not one (rulebook 3.2.1, 3.2.3).
        //
        // The two the game prints are about different halves of the equation -
        // "No single" about the set holding the card, "Other side must be Cog"
        // about the set facing it - so nothing stops a card printing both, and
        // a single column could only ever hold whichever was written last.
        //
        // Restricted also names a suit, which a bare enum cannot carry. Each
        // entry is therefore {marking, suit}, shaped by App\Support\CardMarking.
        Schema::table('research_cards', function (Blueprint $table) {
            $table->json('markings')->nullable()->after('value');
        });

        DB::table('research_cards')
            ->whereNotNull('restriction')
            ->orderBy('id')
            ->chunkById(500, function ($cards) {
                foreach ($cards as $card) {
                    $marking = ResearchCardMarking::tryFrom((string) $card->restriction);

                    // Only No single could ever have been stored, and it names
                    // no suit, so every existing row converts cleanly.
                    if ($marking === null || $marking->namesASuit()) {
                        continue;
                    }

                    DB::table('research_cards')
                        ->where('id', $card->id)
                        ->update([
                            'markings' => json_encode(CardMarking::listToArray([
                                new CardMarking($marking),
                            ])),
                        ]);
                }
            });

        Schema::table('research_cards', function (Blueprint $table) {
            $table->dropColumn('restriction');
        });
    }

    public function down(): void
    {
        Schema::table('research_cards', function (Blueprint $table) {
            $table->string('restriction')->nullable()->after('value');
        });

        DB::table('research_cards')
            ->whereNotNull('markings')
            ->orderBy('id')
            ->chunkById(500, function ($cards) {
                foreach ($cards as $card) {
                    $markings = CardMarking::listFrom(json_decode((string) $card->markings, true));

                    // One column, one marking: the first that names no suit is
                    // the only kind it could ever hold.
                    foreach ($markings as $marking) {
                        if (! $marking->marking->namesASuit()) {
                            DB::table('research_cards')
                                ->where('id', $card->id)
                                ->update(['restriction' => $marking->marking->value]);

                            break;
                        }
                    }
                }
            });

        Schema::table('research_cards', function (Blueprint $table) {
            $table->dropColumn('markings');
        });
    }
};
