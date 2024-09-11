<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('users')->delete();
        
        \DB::table('users')->insert(array (
            2 => 
            array (
                'id' => 2,
                'role' => 0,
                'address' => '0xbc1bd4ddcf6d481e58862815553a2d78932b2c2b',
                'nickname' => 'danial',
                'avatar_url' => 'https://models.readyplayer.me/66d9b76556f5632cd704d4c0.glb?quality=low&meshLod=0',
                'coordinates' => NULL,
                'current_mission' => 8,
                'referrer_id' => NULL,
                'referral_code' => '2',
                'remember_token' => NULL,
                'created_at' => '2024-09-05 13:50:49',
                'updated_at' => '2024-09-05 13:52:01',
            ),
            3 => 
            array (
                'id' => 3,
                'role' => 0,
                'address' => '0xb080c992d5156c524e37d47cf008e83901f2a225',
                'nickname' => 'Charger',
                'avatar_url' => 'https://models.readyplayer.me/66d9b7c0ecae607181ae18d7.glb?quality=low&meshLod=0',
                'coordinates' => NULL,
                'current_mission' => 8,
                'referrer_id' => NULL,
                'referral_code' => '3',
                'remember_token' => NULL,
                'created_at' => '2024-09-05 13:52:43',
                'updated_at' => '2024-09-05 13:53:17',
            ),
        ));
        
        
    }
}