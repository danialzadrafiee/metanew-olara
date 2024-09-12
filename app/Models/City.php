<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'avatar_spawn_coordinates',
        'car_spawn_coordinates',
        'chestman_spawn_coordinates',
        'fob_spawn_coordinates',
        'marker_spawn_coordinates',
        'gems_spawn_coordinates',
    ];

    protected $casts = [
        'avatar_spawn_coordinates' => 'array',
        'car_spawn_coordinates' => 'array',
        'chestman_spawn_coordinates' => 'array',
        'fob_spawn_coordinates' => 'array',
        'marker_spawn_coordinates' => 'array',
        'gems_spawn_coordinates' => 'array',
    ];
}