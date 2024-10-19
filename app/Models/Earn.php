<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Earn extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_amount',
        'users_count',
        'total_cp_removed',
        'user_distributions',
    ];

    protected $casts = [
        'user_distributions' => 'array',
    ];
}