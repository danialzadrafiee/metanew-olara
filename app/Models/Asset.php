<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'amount', 'locked_amount'];

    protected $appends = ['formatted'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedAttribute()
    {
        return [
            'free' => $this->amount - $this->locked_amount,
            'locked' => $this->locked_amount,
            'total' => $this->amount
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($asset) {
            if (!in_array($asset->type, ['cp', 'bnb', 'meta'])) {
                $asset->amount = floor($asset->amount);
                $asset->locked_amount = floor($asset->locked_amount);
            }
        });
    }
}