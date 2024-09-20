<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackupRestoreTokensSeeder extends Seeder
{
    public function run()
    {
        // Backup existing tokens
        $tokens = DB::table('personal_access_tokens')->get();

        // After migration and other seeds, restore the tokens
        foreach ($tokens as $token) {
            DB::table('personal_access_tokens')->insert((array)$token);
        }
    }
}