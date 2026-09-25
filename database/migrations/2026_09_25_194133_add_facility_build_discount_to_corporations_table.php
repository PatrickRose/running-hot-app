<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Credits off every Facility this Corporation builds. MCM's
        // Construction Leader is the only thing in the game that grants one
        // (2 Credits), and it is a number on the Corporation rather than read
        // off the technology because in practice nobody else ever holds it -
        // Control changes it if that stops being true.
        Schema::table('corporations', function (Blueprint $table) {
            $table->unsignedInteger('facility_build_discount')->default(0)->after('credits');
        });

        // Games already under way get it too, so tonight's MCM is not charged
        // full price because its game was created before the column existed.
        DB::table('corporations')
            ->where('name', 'McCullough Calibrated Mechanical')
            ->update(['facility_build_discount' => 2]);
    }

    public function down(): void
    {
        Schema::table('corporations', function (Blueprint $table) {
            $table->dropColumn('facility_build_discount');
        });
    }
};
