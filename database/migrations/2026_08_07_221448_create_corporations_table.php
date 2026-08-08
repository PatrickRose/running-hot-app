<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // Public knowledge (rulebook 2.3.1).
            $table->integer('stock_price')->default(0);
            $table->integer('income')->default(0);
            $table->integer('political_will')->default(0);

            // Secret knowledge: the corporation's current assets.
            $table->integer('credits')->default(0);

            $table->timestamps();

            $table->unique(['game_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporations');
    }
};
