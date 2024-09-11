<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable PostGIS
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        Schema::create('lands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->string('type')->nullable()->default('normal');
            $table->double('size', 8);
            $table->integer('owner_id')->nullable()->default(0);
            $table->double('fixed_price', 16)->nullable()->default(0);
            $table->boolean('is_in_scratch')->default(false);
            $table->boolean('is_locked')->nullable()->default(false);
            $table->boolean('is_suspend')->nullable()->default(false);
            $table->boolean('is_owner_landlord')->nullable()->default(false);
            $table->integer('building_id')->nullable()->default(0);
            $table->string('building_name')->nullable();
            $table->integer('land_collection_id')->nullable()->default(0);
            $table->timestamps();
        });

        // Add PostGIS columns
        DB::statement('ALTER TABLE lands ADD COLUMN geom geometry(MultiPolygon, 4326)');
        DB::statement('ALTER TABLE lands ADD COLUMN centroid geometry(Point, 4326)');

        // Create spatial indexes
        DB::statement('CREATE INDEX lands_geom_idx ON lands USING GIST (geom)');
        DB::statement('CREATE INDEX lands_centroid_idx ON lands USING GIST (centroid)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lands');
        
        // Optionally, you can drop the PostGIS extension if it's no longer needed
        // DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};