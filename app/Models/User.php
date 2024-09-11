<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Log;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $with = ['assets', 'quests'];
    
    protected $appends = ['formatted_assets'];

    public function getFormattedAssetsAttribute()
    {
        return $this->assets->pluck('formatted', 'type')->toArray();
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

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referrer_id');
    }

    public function allReferrals(): HasMany
    {
        return $this->referrals()->with('allReferrals');
    }

    public function getReferralTreeAttribute()
    {
        return $this->allReferrals->map(function ($referral) {
            return [
                'id' => $referral->id,
                'nickname' => $referral->nickname,
                'referrals' => $referral->referralTree,
            ];
        });
    }

    public function makeOffer(Land $land, int $price): ?Offer
    {
        return $this->offers()->create([
            'land_id' => $land->id,
            'price' => $price,
        ]);
    }


    public function applyReferral(string $referralCode): bool
    {
        if ($this->referrer_id) {
            return false;
        }

        $referrer = User::where('referral_code', $referralCode)->first();
        if (!$referrer || $referrer->id === $this->id) {
            return false;
        }

        $this->referrer_id = $referrer->id;
        return $this->save();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function getAssetAttribute($type)
    {
        $asset = $this->assets->firstWhere('type', $type);
        return [
            'free' => $asset ? $asset->amount - $asset->locked_amount : 0,
            'locked' => $asset ? $asset->locked_amount : 0,
            'total' => $asset ? $asset->amount : 0,
        ];
    }

    public function lockAsset(string $type, int $amount): bool
    {
        Log::debug("Attempting to lock asset. User ID: {$this->id}, Type: {$type}, Amount: {$amount}");

        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->where('type', $type)->lockForUpdate()->first();

            if (!$asset) {
                Log::debug("Asset not found for user {$this->id}, type: {$type}. Attempting to create.");
                $asset = $this->assets()->create(['type' => $type, 'amount' => 0, 'locked_amount' => 0]);
            }

            if ($asset->amount - $asset->locked_amount < $amount) {
                Log::debug("Insufficient funds. User {$this->id}, Available: " . ($asset->amount - $asset->locked_amount) . ", Requested: {$amount}");
                return false;
            }

            $asset->locked_amount += $amount;
            $result = $asset->save();

            if ($result) {
                Log::debug("Successfully locked {$amount} of {$type} for user {$this->id}. New locked amount: {$asset->locked_amount}");
            } else {
                Log::debug("Failed to lock {$amount} of {$type} for user {$this->id}");
            }

            return $result;
        });
    }

    public function unlockAsset(string $type, int $amount): bool
    {
        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->where('type', $type)->lockForUpdate()->first();

            if (!$asset || $asset->locked_amount < $amount) {
                return false;
            }

            $asset->locked_amount -= $amount;
            return $asset->save();
        });
    }

    public function addAsset(string $type, int $amount): bool
    {
        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->firstOrCreate(
                ['type' => $type],
                ['amount' => 0, 'locked_amount' => 0]
            );
            $asset->amount += $amount;
            return $asset->save();
        });
    }
    public function removeAsset(string $type, int $amount): bool
    {
        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->where('type', $type)->lockForUpdate()->first();
            if (!$asset || $asset->amount - $asset->locked_amount < $amount) {
                return false;
            }
            $asset->amount -= $amount;
            return $asset->save();
        });
    }
    public function removeLockedAsset(string $type, int $amount): bool
    {
        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->where('type', $type)->lockForUpdate()->first();

            if (!$asset || $asset->locked_amount < $amount) {
                return false;
            }

            $asset->locked_amount -= $amount;
            $asset->amount -= $amount;

            return $asset->save();
        });
    }
    public function transferAsset(User $recipient, string $type, int $amount): bool
    {
        return DB::transaction(function () use ($recipient, $type, $amount) {
            if (!$this->removeAsset($type, $amount)) {
                return false;
            }
            return $recipient->addAsset($type, $amount);
        });
    }

    public function hasSufficientAsset(string $type, int $amount): bool
    {
        $asset = $this->assets->firstWhere('type', $type);
        return $asset && ($asset->amount - $asset->locked_amount) >= $amount;
    }
    public function getAssetsDataAttribute()
    {
        return $this->assets->keyBy('type')->map(function ($asset) {
            return [
                'total' => $asset->amount,
                'free' => $asset->amount - $asset->locked_amount,
                'locked' => $asset->locked_amount
            ];
        });
    }

    public function updateAsset(string $assetType, int $amount): bool
    {
        $asset = $this->assets->firstWhere('type', $assetType);
        if (!$asset) {
            return false;
        }
        $asset->amount = max(0, $asset->amount + $amount);
        return $asset->save();
    }

    public function setAssetExact(string $assetType, int $amount): bool
    {
        $asset = $this->assets->firstWhere('type', $assetType);
        if (!$asset) {
            return false;
        }
        $asset->amount = max(0, $amount);
        return $asset->save();
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
