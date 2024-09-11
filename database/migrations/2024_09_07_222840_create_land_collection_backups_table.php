<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('land_collection_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('land_collection_id');
            $table->json('land_data');
            $table->timestamps();
            $table->foreign('land_collection_id')->references('id')->on('land_collections')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_collection_backups');
    }
};