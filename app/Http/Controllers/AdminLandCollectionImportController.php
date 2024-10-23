<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Enable required extensions safely
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // First drop any conflicting indexes
        $this->dropConflictingIndexes();

        try {
            // Spatial indexes (if don't exist)
            if (!$this->indexExists('lands_geom_gist_idx')) {
                DB::statement('CREATE INDEX lands_geom_gist_idx ON lands USING GIST (geom) WITH (FILLFACTOR = 90)');
            }
            
            if (!$this->indexExists('lands_simplified_geom_idx')) {
                DB::statement('CREATE INDEX lands_simplified_geom_idx ON lands USING GIST (ST_Simplify(geom, 0.01))');
            }

            // BRIN index for sequential scans
            if (!$this->indexExists('lands_id_brin_idx')) {
                DB::statement('CREATE INDEX lands_id_brin_idx ON lands USING BRIN (id) WITH (pages_per_range = 128)');
            }

            // Composite indexes for common queries
            $compositeIndexes = [
                'lands_owner_composite_idx' => 'CREATE INDEX lands_owner_composite_idx ON lands (owner_id, id, fixed_price, type)',
                'lands_collection_composite_idx' => 'CREATE INDEX lands_collection_composite_idx ON lands (land_collection_id, id, owner_id)',
                'lands_price_composite_idx' => 'CREATE INDEX lands_price_composite_idx ON lands (fixed_price, id) WHERE fixed_price > 0',
                'lands_type_composite_idx' => 'CREATE INDEX lands_type_composite_idx ON lands (type, id, owner_id)',
                'lands_updated_at_idx' => 'CREATE INDEX lands_updated_at_idx ON lands (updated_at, id)'
            ];

            foreach ($compositeIndexes as $indexName => $sql) {
                if (!$this->indexExists($indexName)) {
                    DB::statement($sql);
                }
            }

            // Covering indexes
            $coveringIndexes = [
                'lands_owner_lookup_idx' => 'CREATE INDEX lands_owner_lookup_idx ON lands (owner_id, land_collection_id) INCLUDE (fixed_price, type, size, is_locked)',
                'lands_price_lookup_idx' => 'CREATE INDEX lands_price_lookup_idx ON lands (fixed_price) INCLUDE (owner_id, type, size, land_collection_id) WHERE fixed_price > 0',
                'auctions_land_status_idx' => 'CREATE INDEX auctions_land_status_idx ON auctions (land_id, status) INCLUDE (minimum_price, highest_bid)'
            ];

            foreach ($coveringIndexes as $indexName => $sql) {
                if (!$this->indexExists($indexName)) {
                    DB::statement($sql);
                }
            }

            // Attempt to cluster only if the index exists
            if ($this->indexExists('lands_geom_gist_idx')) {
                DB::statement('CLUSTER lands USING lands_geom_gist_idx');
            }

            // Update statistics
            DB::statement('ANALYZE lands');

            // Optimize table parameters
            DB::statement('ALTER TABLE lands SET (autovacuum_vacuum_scale_factor = 0.05)');
            DB::statement('ALTER TABLE lands SET (autovacuum_analyze_scale_factor = 0.02)');
            DB::statement('ALTER TABLE lands SET (fillfactor = 90)');

            // Create statistics if they don't exist
            if (!$this->statisticsExists('lands_multi_stats')) {
                DB::statement('CREATE STATISTICS lands_multi_stats (dependencies) ON owner_id, land_collection_id, fixed_price FROM lands');
            }

        } catch (\Exception $e) {
            // Log the error and continue with other operations
            \Log::error('Error during index creation: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        $indexes = [
            'lands_geom_gist_idx',
            'lands_simplified_geom_idx',
            'lands_id_brin_idx',
            'lands_owner_composite_idx',
            'lands_collection_composite_idx',
            'lands_price_composite_idx',
            'lands_type_composite_idx',
            'lands_updated_at_idx',
            'lands_owner_lookup_idx',
            'lands_price_lookup_idx',
            'auctions_land_status_idx'
        ];

        foreach ($indexes as $indexName) {
            if ($this->indexExists($indexName)) {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            }
        }

        // Drop statistics if they exist
        if ($this->statisticsExists('lands_multi_stats')) {
            DB::statement('DROP STATISTICS IF EXISTS lands_multi_stats');
        }
    }

    private function indexExists(string $indexName): bool
    {
        $result = DB::select("
            SELECT 1 
            FROM pg_indexes 
            WHERE indexname = ?
        ", [$indexName]);

        return !empty($result);
    }

    private function statisticsExists(string $statsName): bool
    {
        $result = DB::select("
            SELECT 1 
            FROM pg_statistic_ext 
            WHERE stxname = ?
        ", [$statsName]);

        return !empty($result);
    }

    private function dropConflictingIndexes(): void
    {
        // Get list of indexes we want to replace
        $conflictingIndexes = [
            'lands_owner_id_idx',
            'lands_collection_id_idx',
            'lands_fixed_price_idx',
            'lands_type_idx'
        ];

        foreach ($conflictingIndexes as $indexName) {
            if ($this->indexExists($indexName)) {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            }
        }
    }
};