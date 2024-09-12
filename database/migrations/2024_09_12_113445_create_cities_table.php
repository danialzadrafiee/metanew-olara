<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('avatar_spawn_coordinates');
            $table->json('car_spawn_coordinates');
            $table->json('chestman_spawn_coordinates');
            $table->json('fob_spawn_coordinates');
            $table->json('marker_spawn_coordinates');
            $table->json('gems_spawn_coordinates');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};