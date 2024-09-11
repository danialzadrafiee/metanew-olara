<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LandCollection extends Model
{
    use HasFactory;

    public function lands(): HasMany
    {
        return $this->hasMany(Land::class);
    }

    public function backup(): HasOne
    {
        return $this->hasOne(LandCollectionBackup::class);
    }
 
}
