<?php

namespace App\Models;

use App\Traits\HasAssets;
use App\Traits\HasReferrals;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasAssets, HasReferrals;

    protected $with = ['assets', 'quests', 'city'];

    protected $appends = ['formatted_assets'];

    public function setAddressAttribute($value)
    {
        $this->attributes['address'] = strtolower($value);
    }

    public function getFormattedAssetsAttribute()
    {
        return $this->assets->pluck('formatted', 'type')->toArray();
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if ($user->id === 1 && $user->nickname !== 'Bank') {
                throw new \Exception('User with ID 1 is reserved for the bank.');
            }

            if ($user->id === 2 && $user->nickname !== 'Foundation') {
                throw new \Exception('User with ID 2 is reserved for the foundation.');
            }

            if ($user->id === 1) {
                $user->role = 'bank';
            } elseif ($user->id === 2) {
                $user->role = 'foundation';
            } else {
                $lastUser = static::where('id', '>', 2)->orderBy('id', 'desc')->first();
                $nextId = $lastUser ? $lastUser->id + 1 : 3; // Start from 3 if no other users exist
                $user->id = $nextId;
                do {
                    $referralCode = Str::random(8);
                } while (static::where('referral_code', $referralCode)->exists());
                $user->referral_code = $referralCode;
            }
        });

        static::created(function ($user) {
            $assetTypes = ['cp', 'meta', 'bnb', 'iron', 'wood', 'sand', 'gold', 'ticket', 'giftbox', 'chest_silver', 'chest_gold', 'chest_diamond', 'scratch_box'];

            foreach ($assetTypes as $type) {
                $user->assets()->create([
                    'type' => $type,
                    'amount' => 0,
                    'locked_amount' => 0,
                ]);
            }
        });
    }

    public function ownedLands(): HasMany
    {
        return $this->hasMany(Land::class, 'owner_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function makeOffer(Land $land, int $price): ?Offer
    {
        return $this->offers()->create([
            'land_id' => $land->id,
            'price' => $price,
        ]);
    }

    public function userRewards()
    {
        return $this->hasMany(UserReward::class);
    }

    public function quests(): BelongsToMany
    {
        return $this->belongsToMany(Quest::class, 'user_quests')
            ->withPivot('completed_at')
            ->withTimestamps();
    }
}