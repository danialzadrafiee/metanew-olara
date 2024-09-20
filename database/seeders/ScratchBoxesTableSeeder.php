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

        \DB::table('scratch_boxes')->insert(array(
            0 =>
            array(
                'name' => 's0',
                'price' => 347.1 * 10 ^ 18,
                'status' => 'sold',
                'user_id' => 3,
                'created_at' => '2024-09-09 20:43:18',
                'updated_at' => '2024-09-09 21:27:36',
            ),
            1 =>
            array(
                'name' => 's1',
                'price' => 379.2 * 10 ^ 18,
                'status' => 'sold',
                'user_id' => 4,
                'created_at' => '2024-09-09 21:27:53',
                'updated_at' => '2024-09-09 21:27:53',
            ),
            2 =>
            array(
                'name' => 's2',
                'price' => 384.6 * 10 ^ 18,
                'status' => 'sold',
                'user_id' => 3,
                'created_at' => '2024-09-09 21:27:53',
                'updated_at' => '2024-09-09 21:27:53',
            ),
            3 =>
            array(
                'name' => 's3',
                'price' => 424.3 * 10 ^ 18,
                'status' => 'sold',
                'user_id' => 3,
                'created_at' => '2024-09-09 20:44:16',
                'updated_at' => '2024-09-09 21:27:53',
            ),
            4 =>
            array(
                'name' => 's4',
                'price' => 417.7 * 10 ^ 18,
                'status' => 'available',
                'user_id' => 0,
                'created_at' => '2024-09-09 21:27:53',
                'updated_at' => '2024-09-09 21:27:53',
            ),
            5 =>
            array(
                'name' => 's5',
                'price' => 370.7 * 10 ^ 18,
                'status' => 'available',
                'user_id' => 0,
                'created_at' => '2024-09-09 21:27:16',
                'updated_at' => '2024-09-09 22:09:58',
            ),
            6 =>
            array(
                'name' => 's6',
                'price' => 382.5 * 10 ^ 18,
                'status' => 'available',
                'user_id' => 0,
                'created_at' => '2024-09-09 20:43:36',
                'updated_at' => '2024-09-09 22:10:01',
            ),
        ));
    }
}
