<?php

namespace Database\Seeders;

use App\Models\User;
use DB;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

        // Insert initial data
        User::create([
            'id' => 1,
            'role' => 1,
            'address' => '0x24015B83f9B2CD8BF831101e79b3BFB9aE20afa1',
            'nickname' => 'Bank',
            'role' => 'bank',
            'referral_code' => '1',
        ]);

        User::create([
            'id' => 2,
            'role' => 'foundation',
            'address' => '0x6Fe6EcF9c1fD4aC7408Cb584AffCE2460AAA4CA9',
            'nickname' => 'Foundation',
            'referral_code' => '2',
        ]);

        $this->call([
            CitySeeder::class
        ]);

        $this->call([
            AuctionsTableSeeder::class,
            UsersTableSeeder::class,
            AssetsTableSeeder::class,
            ScratchBoxesTableSeeder::class,
            ScratchBoxLandTableSeeder::class,
        ]);




        $this->call([
            // LandsTableSeederDubai::class,
            // LandsTableSeederTehran::class,
            // LandCollectionsTableSeeder::class,
            LandCollectionsTableSeederDev::class,
            LandsTableSeederDev::class,
        ]);



        $tokens = DB::table('personal_access_tokens')->get();
        foreach ($tokens as $token) {
            if (DB::table('users')->where('id', $token->tokenable_id)->exists()) {
                DB::table('personal_access_tokens')->insert((array)$token);
            }
        }
    }
}
