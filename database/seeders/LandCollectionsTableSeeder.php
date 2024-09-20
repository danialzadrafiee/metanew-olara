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
        
        
        \DB::table('land_collections')->insert(array (
            0 => 
            array (
                'data' => NULL,
                'file_name' => 'dubai_clean',
                'collection_name' => 'dubai_clean_seed',
                'is_active' => true,
                'is_locked' => false,
                'type' => 'normal',
                'created_at' => '2024-09-13 08:21:16',
                'updated_at' => '2024-09-13 08:21:16',
            ),
            1 => 
            array (
                'data' => NULL,
                'file_name' => 'tehran_clean',
                'collection_name' => 'tehran_clean_seed',
                'is_active' => true,
                'is_locked' => false,
                'type' => 'normal',
                'created_at' => '2024-09-13 08:21:16',
                'updated_at' => '2024-09-13 08:21:16',
            ),
        ));
        
        
    }
}