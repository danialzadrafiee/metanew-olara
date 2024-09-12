<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'id' => 1,
            'role' => 0,
            'address' => '0x0000000000000000000000000000000000',
            'nickname' => 'Bank',
            'avatar_url' => 'https://models.readyplayer.me/66d9b76556f5632cd704d4c0.glb?quality=low&meshLod=0',
            'coordinates' => NULL,
            'current_mission' => 8,
            'referrer_id' => NULL,
            'referral_code' => '1',
            'remember_token' => NULL,
            'created_at' => '2024-09-05 13:50:49',
            'updated_at' => '2024-09-05 13:52:01',
        ]);
        $this->call([
            QuestsTableSeeder::class,
            CitySeeder::class
        ]);

        $this->call([
            // UsersTableSeeder::class,
            // AssetsTableSeeder::class,
            // LandsTableSeeder::class,
            // AuctionsTableSeeder::class,
            // LandCollectionsTableSeeder::class,
            // ScratchBoxesTableSeeder::class,
            // ScratchBoxLandTableSeeder::class,
        ]);
        
        // $tokens = DB::table('personal_access_tokens')->get();
        // foreach ($tokens as $token) {
        //     if (DB::table('users')->where('id', $token->tokenable_id)->exists()) {
        //         DB::table('personal_access_tokens')->insert((array)$token);
        //     }
        // }
    }
}
