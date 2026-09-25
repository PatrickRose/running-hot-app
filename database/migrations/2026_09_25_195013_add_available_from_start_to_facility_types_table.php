<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether every Corporation may build this type without researching
        // it. Rulebook 3.3.1 names Research, Security and Corporate; every
        // other type is unlocked by a technology ("Unlock: Arms facility") or
        // is one Control has invented, and neither is on a Corporation's list
        // until it has earned it.
        Schema::table('facility_types', function (Blueprint $table) {
            $table->boolean('available_from_start')->default(false)->after('build_cost');
        });

        DB::table('facility_types')
            ->whereIn('key', ['research', 'corporate', 'security'])
            ->update(['available_from_start' => true]);
    }

    public function down(): void
    {
        Schema::table('facility_types', function (Blueprint $table) {
            $table->dropColumn('available_from_start');
        });
    }
};
