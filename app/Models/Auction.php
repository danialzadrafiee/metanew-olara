<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;

class Auction extends Model
{
    protected $casts = [
        'end_time' => 'datetime',
    ];

    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->end_time->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->where('end_time', '>', now())->where('status', 'active');
    }

    public function highestBid()
    {
        return $this->bids()->orderBy('amount', 'desc')->first();
    }

    public function getHighestBidAttribute()
    {
        $highestBid = $this->highestBid();
        return $highestBid ? $highestBid->amount : null;
    }

    public function getHighestBidderAttribute()
    {
        $highestBid = $this->highestBid();
        return $highestBid ? $highestBid->user : null;
    }

    public function processAuction()
    {
        DB::beginTransaction();
        try {
            $this->status = 'done';
            $this->save();

            $highestBid = $this->highestBid();
            if ($highestBid) {
                $this->processSuccessfulAuction($highestBid);
            } else {
                $this->processUnsuccessfulAuction();
            }

            DB::commit();
            Log::info("Auction {$this->id} processed successfully. Status: " . ($highestBid ? "Sold" : "No bids"));
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error processing auction {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    private function processSuccessfulAuction($highestBid)
    {
        $land = $this->land;
        $seller = User::findOrFail($this->owner_id);
        $buyer = $highestBid->user;

        $land->owner_id = $buyer->id;
        $land->fixed_price = 0;
        $land->save();

        if (!$seller->addAsset('bnb', $highestBid->amount)) {
            throw new Exception("Failed to add BNB to seller");
        }
        if (!$buyer->removeLockedAsset('bnb', $highestBid->amount)) {
            throw new Exception("Failed to remove BNB from buyer");
        }

      

        $this->unlockOtherBids($buyer->id);
    }

    private function processUnsuccessfulAuction()
    {
        $land = $this->land;
        $land->fixed_price = 0;
        $land->save();
    }

    private function unlockOtherBids($winningBidderId)
    {
        $otherBids = $this->bids()->where('user_id', '!=', $winningBidderId)->get();
        foreach ($otherBids as $bid) {
            if (!$bid->user->unlockAsset('bnb', $bid->amount)) {
                throw new Exception("Failed to unlock BNB for user {$bid->user_id}");
            }
        }
    }

    public static function processCanceledAuction($canceledAuction)
    {
        DB::beginTransaction();
        try {
            $land = $canceledAuction->land;
            $land->fixed_price = 0;
            $land->save();

            $canceledAuction->status = 'done';
            $canceledAuction->save();

            DB::commit();
            Log::info("Canceled auction {$canceledAuction->id} processed successfully.");
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error processing canceled auction {$canceledAuction->id}: " . $e->getMessage());
            return false;
        }
    }
}