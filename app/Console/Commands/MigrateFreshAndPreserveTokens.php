<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateFreshAndPreserveTokens extends Command
{
    protected $signature = 'migrate:kt';
    protected $description = 'Drop all tables, re-run all migrations, seed, and restore tokens';

    public function handle()
    {
        // Backup tokens
        $tokens = [];
        if (Schema::hasTable('personal_access_tokens')) {
            $tokens = DB::table('personal_access_tokens')->get()->toArray();
        }

        // Run migrate:fresh
        $this->info('Running migrate:fresh...');
        $this->call('migrate:fresh');

        // Seed the database
        $this->info('Seeding the database...');
        $this->call('db:seed');

        // Restore tokens
        $this->info('Restoring tokens...');
        foreach ($tokens as $token) {
            if (DB::table('users')->where('id', $token->tokenable_id)->exists()) {
                DB::table('personal_access_tokens')->insert((array)$token);
            }
        }

        $this->info('All done!');
    }
}