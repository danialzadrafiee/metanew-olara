<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetsTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('assets')->truncate();

        $users = DB::table('users')->pluck('id');

        $regularAssetTypes = [
            'bnb' => 5,
            'meta' => 0,
            'cp' => 0,
            'wood' => 1000,
            'iron' => 0,
            'sand' => 100,  
            'gold' => 0,
            'scratch_box' => 2,
        ];

        $specialAssetTypes = [
            'bnb' => 0,
            'meta' => 0,
        ];

        foreach ($users as $userId) {
            if ($userId <= 2) {
                // For users 1 and 2, only add bnb and meta with 0 amount
                foreach ($specialAssetTypes as $type => $amount) {
                    DB::table('assets')->insert([
                        'user_id' => $userId,
                        'type' => $type,
                        'amount' => $amount,
                        'locked_amount' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                // For users 3 and above, add all asset types
                foreach ($regularAssetTypes as $type => $amount) {
                    DB::table('assets')->insert([
                        'user_id' => $userId,
                        'type' => $type,
                        'amount' => $amount,
                        'locked_amount' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}