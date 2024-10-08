<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        $city = 2;
        $mission = 8;

        User::create([
            'id' => 3,
            'role' => 0,
            'address' => '0x83C0Ec776a36Cde3f51250018837cfaa3D17B9d6',
            'nickname' => 'danial',
            'city_id' => $city,
            'avatar_url' => 'https://models.readyplayer.me/66d9b76556f5632cd704d4c0.glb?quality=low&meshLod=0',
            'current_mission' => $mission,
            'created_at' => '2024-09-05 13:50:49',
            'updated_at' => '2024-09-05 13:52:01',
        ]);

        User::create([
            'id' => 4,
            'role' => 0,
            'city_id' => $city,
            'address' => '0xab229fC3B342028B5d6323B6f9D23594E7fa1c9b',
            'nickname' => 'Charger',
            'avatar_url' => 'https://models.readyplayer.me/66d9b7c0ecae607181ae18d7.glb?quality=low&meshLod=0',
            'current_mission' => $mission,
            'created_at' => '2024-09-05 13:52:43',
            'updated_at' => '2024-09-05 13:53:17',
        ]);
    }
}