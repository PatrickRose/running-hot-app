<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The technologies on the Corporations' tech trees (rulebook 3.2.2).
        //
        // This is the catalogue only - what can be researched, and what it
        // costs. Which Corporation has actually researched one, and which
        // Facility is storing it, belong to the research game and to Facility
        // storage, neither of which is built yet.
        //
        // Per game and editable, because the rulebook has players writing
        // custom research proposals that Research Control prices and adds to the
        // tree during play (3.2.4).
        Schema::create('technology_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            // The code printed on the card (RSR001, RGR069). Nullable for a
            // technology Control adds mid-game, which has none.
            $table->string('code')->nullable();

            $table->string('name');

            // Which tree the technology sits on: one Corporation's own, or the
            // set common to all of them. Kept as the key from the card sheet
            // alongside the resolved Corporation, so a technology whose
            // Corporation is not in this game still says which tree it came
            // from.
            $table->string('tree');
            $table->foreignId('corporation_id')->nullable()->constrained()->nullOnDelete();

            $table->text('description')->nullable();

            // What researching it gets you, as printed. Some of these are
            // mechanical - "Unlock: Keresh" makes a Protection Card buyable,
            // "Unlock: Power facility" adds a Facility type - but the research
            // game does not exist to act on them, so they are words Control
            // reads and acts on, exactly like a Facility type's effect text.
            $table->text('effect')->nullable();

            // The price in the four Research Point suits (3.2.2). Most
            // technologies are free in two or three of them, so zero is a real
            // price rather than a missing one.
            $table->unsignedInteger('cog_cost')->default(0);
            $table->unsignedInteger('brain_cost')->default(0);
            $table->unsignedInteger('leaf_cost')->default(0);
            $table->unsignedInteger('maths_cost')->default(0);

            // The technologies that have to be researched first, held as the
            // titles printed on the card rather than as foreign keys. A title is
            // what a Research player shows Research Control, and Control may add
            // a technology that others already name as a prerequisite, so a
            // prerequisite has to be nameable before it exists.
            $table->json('prerequisites');

            // Where the resulting card has to be housed, if the card says so
            // (3.2.2, footnote 7) - a technology with no Facility that can hold
            // it may not be researched at all.
            $table->foreignId('required_facility_type_id')->nullable()->constrained('facility_types')->nullOnDelete();

            // What a Runner has to beat to copy or destroy it (3.2.6). Null on
            // the handful of rows that are instructions to Research Control
            // rather than cards a Run can reach.
            $table->unsignedInteger('copy_strength')->nullable();
            $table->unsignedInteger('destroy_strength')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['game_id', 'code']);
            $table->index(['game_id', 'tree']);
            $table->index(['game_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technology_types');
    }
};
