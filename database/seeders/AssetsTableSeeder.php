<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetsTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('assets')->truncate();

        $assets = [
            ['user_id' => 2, 'type' => 'bnb', 'amount' => 1000],
            ['user_id' => 2, 'type' => 'cp', 'amount' => 2000],
            ['user_id' => 2, 'type' => 'wood', 'amount' => 2000],
            ['user_id' => 2, 'type' => 'iron', 'amount' => 1500],
            ['user_id' => 2, 'type' => 'sand', 'amount' => 3000],
            ['user_id' => 2, 'type' => 'gold', 'amount' => 500],
            ['user_id' => 2, 'type' => 'scratch_box', 'amount' => 2],
            ['user_id' => 3, 'type' => 'bnb', 'amount' => 50000],
            ['user_id' => 3, 'type' => 'cp', 'amount' => 1000],
            ['user_id' => 3, 'type' => 'wood', 'amount' => 1000],
            ['user_id' => 3, 'type' => 'iron', 'amount' => 800],
            ['user_id' => 3, 'type' => 'sand', 'amount' => 1500],
            ['user_id' => 3, 'type' => 'gold', 'amount' => 250],
            ['user_id' => 3, 'type' => 'scratch_box', 'amount' => 2],
        ];

        foreach ($assets as $asset) {
            DB::table('assets')->insert(array_merge($asset, [
                'locked_amount' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}