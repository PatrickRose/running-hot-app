<?php

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
            $table->text('description')->nullable();

            // The mechanical effects, as data so a type Control invents can
            // carry them. Both are "per Facility of this type, added to every
            // Facility the Corporation owns".
            $table->unsignedInteger('protection_slots_granted')->default(0);
            $table->unsignedInteger('technology_capacity_granted')->default(0);

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
