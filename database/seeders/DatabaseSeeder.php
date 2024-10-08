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
            'role' => 'bank',
            'address' => env('BANK_ADDRESS'),
            'nickname' => 'Bank',
            'referral_code' => '1',
        ]);

        User::create([
            'id' => 2,
            'role' => 'foundation',
            'address' => env('FOUNDATION_ADDRESS'),
            'nickname' => 'Foundation',
            'referral_code' => '2',
        ]);


        $this->call([
            CitySeeder::class
        ]);

        $this->call([
            // AuctionsTableSeeder::class,
            UsersTableSeeder::class,
            // LandCollectionsTableSeeder::class,
            AssetsTableSeeder::class,
            // ScratchBoxesTableSeeder::class,
            // ScratchBoxLandTableSeeder::class,
        ]);
        $this->call(LandsTableSeeder::class);
        $this->call(LandCollectionsTableSeeder::class);




        $this->call([
            // LandsTableSeederDubai::class,
            // LandsTableSeederTehran::class,
            // LandCollectionsTableSeeder::class,
            // LandCollectionsTableSeederDev::class,
            // LandsTableSeederDev::class,
        ]);



        // $tokens = DB::table('personal_access_tokens')->get();
        // foreach ($tokens as $token) {
        //     if (DB::table('users')->where('id', $token->tokenable_id)->exists()) {
        //         DB::table('personal_access_tokens')->insert((array)$token);
        //         $this->call(LandCollectionsTableSeeder::class);
    }
}
