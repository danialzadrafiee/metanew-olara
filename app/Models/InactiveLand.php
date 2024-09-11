<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InactiveLand extends Model
{

    public function landCollection()
    {
        return $this->belongsTo(LandCollection::class);
    }
}