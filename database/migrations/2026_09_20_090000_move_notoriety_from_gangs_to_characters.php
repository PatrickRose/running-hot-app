<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notoriety belongs to a Runner, and a gang's is the total of its members'.
 *
 * The rulebook heads the section "Gang Notoriety" and the glossary calls it "a
 * measure of how well-known and successful your gang is", which is what this
 * application modelled: one number on the gang. The designer's ruling is that
 * the number is earned by the person - "as members of your gang successfully
 * complete runs or perform other actions, the Notoriety of your gang will go
 * up" - so it is carried per character and the gang's figure is the roll-up.
 *
 * That makes the gang's number **derived**, which is why the column goes rather
 * than being kept in step: a stored total beside the rows it sums is a second
 * source of truth that can disagree with them, and slots and storage are
 * already derived here for the same reason.
 *
 * Nothing is carried across, and it cannot be: a gang's total says nothing
 * about which of its five Runners earned it, so there is no honest way to split
 * one back out. Every game the application seeds starts every gang at nought,
 * so this loses nothing except in a game already in flight - where Control
 * types the figures back in per Runner, which is the shape they are now in
 * anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // Unsigned nowhere and unbounded in App\Enums\Tracker for the same
            // reason: the rulebook has the press running bad stories, after
            // which "your Notoriety will fall" (2.3.2), and Control decides how
            // far.
            $table->integer('notoriety')->default(0)->after('tags');
        });

        Schema::table('gangs', function (Blueprint $table) {
            $table->dropColumn('notoriety');
        });
    }

    public function down(): void
    {
        Schema::table('gangs', function (Blueprint $table) {
            $table->integer('notoriety')->default(0);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('notoriety');
        });
    }
};
