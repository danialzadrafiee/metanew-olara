<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earns', function (Blueprint $table) {
            $table->id();
            $table->decimal('meta_amount', 18, 8);
            $table->integer('users_count');
            $table->decimal('total_cp_removed', 18, 8);
            $table->json('user_distributions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earns');
    }
};