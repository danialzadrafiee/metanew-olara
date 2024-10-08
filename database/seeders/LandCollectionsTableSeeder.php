<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LandCollectionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('land_collections')->delete();
        
        \DB::table('land_collections')->insert(array (
            0 => 
            array (
                'id' => 1,
                'data' => NULL,
                'file_name' => 'g1',
                'region' => 'R1',
                'city' => 'Tehran',
                'collection_name' => 'g1 -- 10-06-2024',
                'is_active' => true,
                'is_locked' => false,
                'type' => 'normal',
                'created_at' => '2024-10-06 13:08:44',
                'updated_at' => '2024-10-06 13:08:44',
            ),
            1 => 
            array (
                'id' => 2,
                'data' => NULL,
                'file_name' => 'g2',
                'region' => 'R2',
                'city' => 'Tehran',
                'collection_name' => 'g2 -- 10-06-2024',
                'is_active' => true,
                'is_locked' => false,
                'type' => 'normal',
                'created_at' => '2024-10-06 13:08:51',
                'updated_at' => '2024-10-06 13:08:51',
            ),
            2 => 
            array (
                'id' => 3,
                'data' => NULL,
                'file_name' => 'g3',
                'region' => 'R3',
                'city' => 'Tehran',
                'collection_name' => 'g3 -- 10-06-2024',
                'is_active' => true,
                'is_locked' => false,
                'type' => 'normal',
                'created_at' => '2024-10-06 13:08:58',
                'updated_at' => '2024-10-06 13:08:58',
            ),
        ));
        
        
    }
}