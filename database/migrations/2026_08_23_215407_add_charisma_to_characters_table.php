<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Runners are written up with four skills, not three. The rulebook only
     * exercises Brawn and Hack in Run challenges (3.4.3) and Body in the
     * incapacitation check (3.4.2), so Charisma is carried as character detail
     * rather than as a Tracker Control moves.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('charisma')->default(0)->after('hack');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('charisma');
        });
    }
};
