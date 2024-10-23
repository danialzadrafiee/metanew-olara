<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Enable required extensions safely
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        try {
            // Composite indexes for lands with INCLUDE clauses for better covering
            $landIndexes = [
                'lands_owner_composite_idx' => 'CREATE INDEX lands_owner_composite_idx ON lands (owner_id, id, fixed_price, type) INCLUDE (size, land_collection_id)',
                'lands_collection_composite_idx' => 'CREATE INDEX lands_collection_composite_idx ON lands (land_collection_id, id, owner_id) INCLUDE (fixed_price, type)',
                'lands_price_composite_idx' => 'CREATE INDEX lands_price_composite_idx ON lands (fixed_price, id) INCLUDE (owner_id, type) WHERE fixed_price > 0',
                'lands_type_composite_idx' => 'CREATE INDEX lands_type_composite_idx ON lands (type, id, owner_id) INCLUDE (fixed_price)',
                'lands_updated_at_idx' => 'CREATE INDEX lands_updated_at_idx ON lands (updated_at, id) INCLUDE (owner_id)'
            ];

            foreach ($landIndexes as $indexName => $sql) {
                if (!$this->indexExists($indexName)) {
                    DB::statement($sql);
                }
            }

            // Enhanced covering indexes for lands
            $landCoveringIndexes = [
                'lands_owner_lookup_idx' => 'CREATE INDEX lands_owner_lookup_idx ON lands (owner_id, land_collection_id) INCLUDE (fixed_price, type, size, is_locked, updated_at)',
                'lands_price_lookup_idx' => 'CREATE INDEX lands_price_lookup_idx ON lands (fixed_price) INCLUDE (owner_id, type, size, land_collection_id, is_locked) WHERE fixed_price > 0',
                'lands_id_btree_idx' => 'CREATE INDEX lands_id_btree_idx ON lands USING btree (id)',
                'lands_collection_price_idx' => 'CREATE INDEX lands_collection_price_idx ON lands (land_collection_id, fixed_price) INCLUDE (owner_id, size) WHERE fixed_price > 0'
            ];

            foreach ($landCoveringIndexes as $indexName => $sql) {
                if (!$this->indexExists($indexName)) {
                    DB::statement($sql);
                }
            }

            // Enhanced auction indexes
            $auctionIndexes = [
                'auctions_land_status_idx' => 'CREATE INDEX auctions_land_status_idx ON auctions (land_id, status, end_time) INCLUDE (minimum_price)',
                'auctions_active_lookup_idx' => 'CREATE INDEX auctions_active_lookup_idx ON auctions (status, end_time) INCLUDE (land_id, minimum_price, owner_id) WHERE status = \'active\'',
                'auctions_end_time_idx' => 'CREATE INDEX auctions_end_time_idx ON auctions (end_time, status) INCLUDE (land_id)',
                'auctions_owner_idx' => 'CREATE INDEX auctions_owner_idx ON auctions (owner_id, status) INCLUDE (land_id, end_time)'
            ];

            foreach ($auctionIndexes as $indexName => $sql) {
                if (!$this->indexExists($indexName)) {
                    DB::statement($sql);
                }
            }

            // Enhanced auction bids indexes
            if (Schema::hasTable('auction_bids')) {
                $bidIndexes = [
                    'auction_bids_auction_amount_idx' => 'CREATE INDEX auction_bids_auction_amount_idx ON auction_bids (auction_id, amount DESC) INCLUDE (user_id, created_at)',
                    'auction_bids_user_idx' => 'CREATE INDEX auction_bids_user_idx ON auction_bids (user_id, auction_id) INCLUDE (amount, created_at)',
                    'auction_bids_recent_idx' => 'CREATE INDEX auction_bids_recent_idx ON auction_bids (auction_id, created_at DESC) INCLUDE (amount, user_id)',
                    'auction_bids_status_idx' => 'CREATE INDEX auction_bids_status_idx ON auction_bids (status, auction_id) INCLUDE (amount, user_id)'
                ];

                foreach ($bidIndexes as $indexName => $sql) {
                    if (!$this->indexExists($indexName)) {
                        DB::statement($sql);
                    }
                }
            }

            // Improved clustering strategy
            if ($this->indexExists('lands_geom_idx')) {
                DB::statement('CLUSTER lands USING lands_geom_idx');
            } elseif ($this->indexExists('lands_geom_gist_idx')) {
                DB::statement('CLUSTER lands USING lands_geom_gist_idx');
            } else {
                DB::statement('CLUSTER lands USING lands_owner_composite_idx');
            }

            // Enhanced table statistics
            DB::statement('ANALYZE VERBOSE lands');
            DB::statement('ANALYZE VERBOSE auctions');
            if (Schema::hasTable('auction_bids')) {
                DB::statement('ANALYZE VERBOSE auction_bids');
            }

            // Optimized table parameters for large dataset
            DB::statement('ALTER TABLE lands SET (autovacuum_vacuum_scale_factor = 0.01)');
            DB::statement('ALTER TABLE lands SET (autovacuum_analyze_scale_factor = 0.005)');
            DB::statement('ALTER TABLE lands SET (autovacuum_vacuum_threshold = 1000)');
            DB::statement('ALTER TABLE lands SET (autovacuum_analyze_threshold = 1000)');
            DB::statement('ALTER TABLE lands SET (fillfactor = 85)');

            // Enhanced multi-column statistics
            if (!$this->statisticsExists('lands_multi_stats')) {
                DB::statement('CREATE STATISTICS lands_multi_stats (dependencies) ON owner_id, land_collection_id, fixed_price, type FROM lands');
            }
            if (!$this->statisticsExists('lands_price_stats')) {
                DB::statement('CREATE STATISTICS lands_price_stats (dependencies) ON fixed_price, size, type FROM lands');
            }

            // Additional specialized indexes for common queries
            DB::statement('CREATE INDEX lands_bank_sales_idx ON lands (id, fixed_price) WHERE owner_id = 1 AND fixed_price > 0');
            DB::statement('CREATE INDEX lands_active_sales_idx ON lands (fixed_price, id) WHERE fixed_price > 0 AND is_locked = false');
            DB::statement('CREATE INDEX lands_collection_type_idx ON lands (land_collection_id, type) INCLUDE (owner_id, fixed_price)');

        } catch (\Exception $e) {
            \Log::error('Error during index creation: ' . $e->getMessage());
            throw $e;
        }
    }

    public function down(): void
    {
        $indexes = [
            'lands_owner_composite_idx',
            'lands_collection_composite_idx',
            'lands_price_composite_idx',
            'lands_type_composite_idx',
            'lands_updated_at_idx',
            'lands_owner_lookup_idx',
            'lands_price_lookup_idx',
            'lands_id_btree_idx',
            'lands_collection_price_idx',
            'lands_bank_sales_idx',
            'lands_active_sales_idx',
            'lands_collection_type_idx',
            'auctions_land_status_idx',
            'auctions_active_lookup_idx',
            'auctions_end_time_idx',
            'auctions_owner_idx',
            'auction_bids_auction_amount_idx',
            'auction_bids_user_idx',
            'auction_bids_recent_idx',
            'auction_bids_status_idx'
        ];

        foreach ($indexes as $indexName) {
            if ($this->indexExists($indexName)) {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            }
        }

        // Drop all statistics
        $statistics = ['lands_multi_stats', 'lands_price_stats'];
        foreach ($statistics as $statsName) {
            if ($this->statisticsExists($statsName)) {
                DB::statement("DROP STATISTICS IF EXISTS {$statsName}");
            }
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
};