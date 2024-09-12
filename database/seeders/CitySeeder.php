<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            [
                'name' => 'London',
                'avatar_spawn_coordinates' => [-0.1276, 51.5074],
                'car_spawn_coordinates' => [-0.1276, 51.5074],
                'chestman_spawn_coordinates' => [-0.1276, 51.5074],
                'fob_spawn_coordinates' => [-0.1276, 51.5074],
                'marker_spawn_coordinates' => [-0.1276, 51.5074],
                'gems_spawn_coordinates' => [
                    [-0.1276, 51.5074],
                    [-0.1277, 51.5075],
                    [-0.1278, 51.5076],
                ],
            ],
            [
                'name' => 'Dubai',
                'avatar_spawn_coordinates' => [55.2708, 25.2048],
                'car_spawn_coordinates' => [55.2708, 25.2048],
                'chestman_spawn_coordinates' => [55.2708, 25.2048],
                'fob_spawn_coordinates' => [55.2708, 25.2048],
                'marker_spawn_coordinates' => [55.2708, 25.2048],
                'gems_spawn_coordinates' => [
                    [55.2708, 25.2048],
                    [55.2709, 25.2049],
                    [55.2710, 25.2050],
                ],
            ],
            [
                'name' => 'Tehran',
                'avatar_spawn_coordinates' => [51.3890, 35.6892],
                'car_spawn_coordinates' => [51.3890, 35.6892],
                'chestman_spawn_coordinates' => [51.3890, 35.6892],
                'fob_spawn_coordinates' => [51.3890, 35.6892],
                'marker_spawn_coordinates' => [51.3890, 35.6892],
                'gems_spawn_coordinates' => [
                    [51.3890, 35.6892],
                    [51.3891, 35.6893],
                    [51.3892, 35.6894],
                ],
            ],
        ];

        foreach ($cities as $city) {
            City::create($city);
        }
    }
}