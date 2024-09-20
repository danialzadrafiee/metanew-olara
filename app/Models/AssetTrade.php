<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetTrade extends Model
{
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}