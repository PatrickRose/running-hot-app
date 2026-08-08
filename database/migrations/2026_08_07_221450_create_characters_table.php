<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('corporation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gang_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('role');

            // Skills. Not exercised by the turn engine, but a character is not
            // meaningfully defined without them.
            $table->unsignedInteger('brawn')->default(0);
            $table->unsignedInteger('hack')->default(0);
            $table->unsignedInteger('body')->default(1);

            $table->integer('credits')->default(0);
            $table->unsignedInteger('wounds')->default(0);
            $table->unsignedInteger('tags')->default(0);

            $table->timestamps();

            $table->index(['game_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
