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

class User extends Authenticatable
{
    use HasApiTokens, HasAssets, HasReferrals;

    protected $with = ['assets', 'quests', 'city'];

    protected $appends = ['formatted_assets'];

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

            if ($user->id !== 1) {
                $lastUser = static::where('id', '>', 1)->orderBy('id', 'desc')->first();
                $nextId = $lastUser ? $lastUser->id + 1 : 2; // Start from 2 if no other users exist
                $user->id = $nextId;
                $user->referral_code = $nextId;
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

    public static function getBank()
    {
        $bank = self::firstOrCreate(
            ['id' => 1],
            [
                'address' => '0x0000000000000000000000000000000000000000',
                'nickname' => 'Bank',
                'avatar_url' => null,
                'coordinates' => null,
                'current_mission' => 0,
                'referral_code' => 'BANK',
            ]
        );

        $assetTypes = ['cp', 'cp_locked', 'meta', 'meta_locked', 'iron', 'wood', 'sand', 'gold', 'ticket', 'giftbox', 'chest_silver', 'chest_gold', 'chest_diamond', 'scratch_box'];

        foreach ($assetTypes as $type) {
            $bank->assets()->firstOrCreate(['type' => $type], ['amount' => 0]);
        }

        return $bank;
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
