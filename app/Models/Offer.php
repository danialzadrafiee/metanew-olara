<?php

namespace App\Models;

use Auth;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    protected $with = ['land:id,owner_id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($offer) {
            $user = $offer->user;
            if (!$user) {
                throw new \Exception('User not found for this offer.');
            }
            
            $bnbAsset = $user->getAssetAttribute('bnb');
            if ($bnbAsset['free'] < $offer->price) {
                throw new \Exception('Insufficient BNB to place the offer.');
            }
            
            if (!$user->lockAsset('bnb', $offer->price)) {
                throw new \Exception('Failed to lock BNB for the offer.');
            }
        });

        static::updating(function ($offer) {
            $user = $offer->user;
            $originalPrice = $offer->getOriginal('price');
            if ($offer->isDirty('price')) {
                $newPrice = $offer->price;
                $priceDifference = $newPrice - $originalPrice;
                if ($priceDifference > 0) {
                    if (!$user->lockAsset('bnb', $priceDifference)) {
                        throw new \Exception('Insufficient BNB to update the offer.');
                    }
                } else {
                    $user->unlockAsset('bnb', abs($priceDifference));
                }
            }
        });

        static::deleted(function ($offer) {
            if (!$offer->is_accepted) {
                $user = $offer->user;
                $user->unlockAsset('bnb', $offer->price);
            }
        });
    }

    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accept()
    {
        return DB::transaction(function () {
            $land = $this->land;
            $buyer = $this->user;
            $seller = $land->owner;

            $land->transfer($buyer->id);

            // Transfer BNB
            $buyer->removeLockedAsset('bnb', $this->price);
            $seller->addAsset('bnb', $this->price);

            // Mark offer as accepted
            $this->update(['is_accepted' => true]);

            // Cancel all other offers for this land
            $cancelledOffers = $land->offers()->where('id', '!=', $this->id)->get();
            foreach ($cancelledOffers as $otherOffer) {
                $otherOffer->user->unlockAsset('bnb', $otherOffer->price);
                $otherOffer->delete();
            }

            return $land->fresh()->load('owner');
        });
    }
}