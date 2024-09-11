<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_listings', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('asset_type');
            $table->integer('amount');
            $table->decimal('price_in_bnb', 18, 8);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_listings');
    }
};