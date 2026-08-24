<?php

use App\Enums\FacilityGrantScaling;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per game rather than a global list or an enum: the rulebook's footnote
        // to 3.3.1 says more Facility types may be researched during the game,
        // so Control has to be able to add one mid-game to one game only.
        Schema::create('facility_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            $table->string('key');
            $table->string('name');

            // The type sheet's Effect and Access effect columns, held as words
            // because most of what they describe belongs to a sub-game this
            // application has not built. Control reads them; nothing computes
            // them.
            $table->text('description')->nullable();
            $table->text('access_effect')->nullable();

            $table->unsignedInteger('build_cost')->default(0);

            // The effects the application does compute. Each is "per Facility
            // of this type, added to every Facility the Corporation owns".
            //
            // Physical and cyber are separate because Security grants 1 and 2
            // respectively: rulebook 3.3.4 says "1 more of each type" and the
            // game's own type sheet supersedes it.
            $table->unsignedInteger('physical_slots_granted')->default(0);
            $table->unsignedInteger('cyber_slots_granted')->default(0);
            $table->unsignedInteger('technology_capacity_granted')->default(0);
            $table->unsignedInteger('card_move_discount')->default(0);

            // Whether those effects add up per Facility, or step at 2, 3, 5, 8.
            $table->string('grant_scaling')->default(FacilityGrantScaling::PerFacility->value);

            $table->timestamps();

            $table->unique(['game_id', 'key']);
            $table->unique(['game_id', 'name']);
        });

        // Games that already exist get the starting catalogue, so opening an
        // in-progress game's panel does not show an empty list.
        $now = now();

        foreach (DB::table('games')->pluck('id') as $gameId) {
            DB::table('facility_types')->insert(array_map(
                fn (array $type): array => [
                    ...$type,
                    'grant_scaling' => $type['grant_scaling']->value,
                    'game_id' => $gameId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                FacilityTypeBlueprint::defaults(),
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_types');
    }
};
