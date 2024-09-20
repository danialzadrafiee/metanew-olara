<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{

    protected $casts = [
        'rewards' => 'array',
        'costs' => 'array',
    ];

    
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_quests')
            ->withPivot('completed_at')
            ->withTimestamps();
    }
}