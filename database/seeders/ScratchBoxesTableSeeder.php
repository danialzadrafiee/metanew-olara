<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ScratchBoxesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('scratch_boxes')->delete();
        
        \DB::table('scratch_boxes')->insert(array (
            0 => 
            array (
                'name' => 's0',
                'price' => '347.10',
                'status' => 'sold',
                'user_id' => 2,
                'created_at' => '2024-09-09 20:43:18',
                'updated_at' => '2024-09-09 21:27:36',
            ),
            1 => 
            array (
                'name' => 's1',
                'price' => '379.20',
                'status' => 'sold',
                'user_id' => 2,
                'created_at' => '2024-09-09 20:43:30',
                'updated_at' => '2024-09-09 21:27:48',
            ),
            2 => 
            array (
                'name' => 's2',
                'price' => '384.60',
                'status' => 'sold',
                'user_id' => 3,
                'created_at' => '2024-09-09 20:44:00',
                'updated_at' => '2024-09-09 21:27:53',
            ),
            3 => 
            array (
                'name' => 's3',
                'price' => '424.30',
                'status' => 'sold',
                'user_id' => 3,
                'created_at' => '2024-09-09 20:44:16',
                'updated_at' => '2024-09-09 22:08:40',
            ),
            4 => 
            array (
                'name' => 's4',
                'price' => '417.70',
                'status' => 'available',
                'user_id' => 0,
                'created_at' => '2024-09-09 20:44:20',
                'updated_at' => '2024-09-09 22:08:44',
            ),
            5 => 
            array (
                'name' => 's5',
                'price' => '370.70',
                'status' => 'available',
                'user_id' => 0,
                'created_at' => '2024-09-09 21:27:16',
                'updated_at' => '2024-09-09 22:09:58',
            ),
            6 => 
            array (
                'name' => 's6',
                'price' => '382.50',
                'status' => 'available',
                'user_id' => 0,
                'created_at' => '2024-09-09 20:43:36',
                'updated_at' => '2024-09-09 22:10:01',
            ),
        ));
        
        
    }
}