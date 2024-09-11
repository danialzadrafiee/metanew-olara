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
                'data' => NULL,
                'file_name' => 'g2',
                'collection_name' => 'G2-COL',
                'is_active' => true,
                'is_locked' => false,
                'type' => 'normal',
                'created_at' => '2024-09-09 20:09:04',
                'updated_at' => '2024-09-09 20:09:04',
            ),
            1 => 
            array (
                'data' => NULL,
                'file_name' => 'g1',
                'collection_name' => 'G1-COL',
                'is_active' => true,
                'is_locked' => false,
                'type' => 'normal',
                'created_at' => '2024-09-09 20:08:58',
                'updated_at' => '2024-09-09 20:10:25',
            ),
            2 => 
            array (
                'data' => NULL,
                'file_name' => 'g3',
                'collection_name' => 'G3-COL',
                'is_active' => true,
                'is_locked' => true,
                'type' => 'normal',
                'created_at' => '2024-09-09 20:09:20',
                'updated_at' => '2024-09-09 20:42:24',
            ),
        ));
        
        
    }
}