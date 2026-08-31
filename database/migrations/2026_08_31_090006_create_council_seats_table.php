<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Corporation's seat at one sitting of the Council (rulebook 3.1.2).
 *
 * Attendance is not technically mandatory, and failing to appear promptly for
 * either phase costs Political Will. The rulebook names no figure and no
 * mechanism, so nothing here is automatic: Control marks the seat and applies
 * the penalty, and the applied-at stamps are what stop the same absence being
 * charged for twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('council_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corporation_id')->constrained()->cascadeOnDelete();

            // Unknown until Control says otherwise: an unmarked seat is not an
            // absent one. See App\Enums\CouncilAttendance.
            $table->string('setup_attendance')->default('unknown');
            $table->string('action_attendance')->default('unknown');

            $table->timestamp('setup_penalty_applied_at')->nullable();
            $table->timestamp('action_penalty_applied_at')->nullable();

            $table->timestamps();

            $table->unique(['council_session_id', 'corporation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_seats');
    }
};
