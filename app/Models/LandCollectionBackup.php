<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandCollectionBackup extends Model
{

    protected $casts = [
        'land_data' => 'array',
    ];

    public function landCollection()
    {
        return $this->belongsTo(LandCollection::class);
    }
}