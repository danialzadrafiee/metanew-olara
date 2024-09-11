<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up(): void
    {
        Schema::create('gift_box_contents', function (Blueprint $table) {
            $table->id();
            $table->integer('gift_box_id');
            $table->string('content_type'); // 'cp', 'meta', 'wood', 'stone', etc.
            $table->integer('amount');
            $table->decimal('probability', 5, 2); // Probability of getting this item (0-100)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_box_contents');
    }
};
