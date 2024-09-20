<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LandTransfer extends Model
{


    public function land()
    {
        return $this->belongsTo(Land::class);
    }
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public static function createTransfer(Land $land, User $seller, User $buyer, string $transferType, string $assetType, float $assetAmount)
    {
        return DB::transaction(function () use ($land, $seller, $buyer, $transferType, $assetType, $assetAmount) {
            $land->increment('transfer_times');
            $shares = self::calculateShares($assetAmount, $seller, $land->transfer_times, $transferType);

            self::create([
                'land_id' => $land->id,
                'seller_user_id' => $seller->id,
                'buyer_user_id' => $buyer->id,
                'transfer_type' => $transferType,
                'asset_type' => $assetType,
                'asset_amount' => $assetAmount,
                'land_transfer_times' => $land->transfer_times,
                'receiver_share_amount' => $shares['receiver'],
                'bank_share_amount' => $shares['bank'],
                'foundation_share_amount' => $shares['foundation'],
                'inviter_share_amount' => $shares['inviter'],
            ]);

            self::removeBuyerAssets($buyer, $transferType, $assetType, $assetAmount);
            self::distributeShares($shares, $assetType, $seller, $transferType);
            self::updateLandOwnership($land, $buyer->id);
            self::rewardCPForTransfer($seller, $buyer);

            return $land->fresh()->load('owner');
        });
    }

    public static function createAuctionTransfer(Land $land, User $seller, User $buyer, float $assetAmount)
    {
        return DB::transaction(function () use ($land, $seller, $buyer, $assetAmount) {
            $land->increment('transfer_times');
            $shares = self::calculateShares($assetAmount, $seller, $land->transfer_times, 'auction');

            self::create([
                'land_id' => $land->id,
                'seller_user_id' => $seller->id,
                'buyer_user_id' => $buyer->id,
                'transfer_type' => 'auction',
                'asset_type' => 'bnb',
                'asset_amount' => $assetAmount,
                'land_transfer_times' => $land->transfer_times,
                'receiver_share_amount' => $shares['receiver'],
                'bank_share_amount' => $shares['bank'],
                'foundation_share_amount' => $shares['foundation'],
                'inviter_share_amount' => $shares['inviter'],
            ]);

            self::distributeShares($shares, 'bnb', $seller, 'auction');
            self::updateLandOwnership($land, $buyer->id);
            self::rewardCPForTransfer($seller, $buyer);

            return $land->fresh()->load('owner');
        });
    }

    private static function calculateShares(float $assetAmount, User $seller, int $transferTimes, string $transferType)
    {
        $shares = [
            'receiver' => 0,
            'bank' => 0,
            'foundation' => 0,
            'inviter' => 0,
            'seller_id' => $seller->id,
            'inviter_id' => $seller->inviter_id,
        ];

        if ($transferType === 'offer' || $transferTimes > 1) {
            $shares['receiver'] = GameEconomySettings::getValue('subsequent_transfer.seller_share') * $assetAmount;
            $shares['bank'] = GameEconomySettings::getValue('subsequent_transfer.bank_share') * $assetAmount;
            $shares['foundation'] = GameEconomySettings::getValue('subsequent_transfer.foundation_share') * $assetAmount;
        } else {
            $shares['foundation'] = GameEconomySettings::getValue('first_transfer.foundation_share') * $assetAmount;
            $shares['bank'] = GameEconomySettings::getValue($seller->inviter_id ? 'first_transfer.bank_share' : 'first_transfer.bank_share_no_inviter') * $assetAmount;
            $shares['inviter'] = $seller->inviter_id ? GameEconomySettings::getValue('first_transfer.inviter_share') * $assetAmount : 0;
            $shares['receiver'] = $assetAmount - ($shares['foundation'] + $shares['bank'] + $shares['inviter']);
        }

        Log::info("Calculated shares for $transferType", $shares);
        return $shares;
    }

    private static function removeBuyerAssets(User $buyer, string $transferType, string $assetType, float $assetAmount)
    {
        $success = $transferType === 'offer' ? $buyer->removeLockedAsset($assetType, $assetAmount) : $buyer->removeAsset($assetType, $assetAmount);
        if (!$success) {
            Log::error("Failed to remove assets from buyer", ['buyer_id' => $buyer->id, 'asset_type' => $assetType, 'asset_amount' => $assetAmount]);
            throw new \Exception("Failed to remove {$assetType} from buyer.");
        }
    }

    private static function distributeShares($shares, $assetType, User $seller, string $transferType)
    {
        $seller->addAsset($assetType, $shares['receiver']);
        User::where('role', 'bank')->first()->addAsset($assetType, $shares['bank']);
        User::where('role', 'foundation')->first()->addAsset($assetType, $shares['foundation']);
        if ($shares['inviter'] > 0 && $seller->inviter) {
            $seller->inviter->addAsset($assetType, $shares['inviter']);
        }
    }

    private static function updateLandOwnership(Land $land, int $newOwnerId)
    {
        $land->update(['owner_id' => $newOwnerId, 'fixed_price' => 0]);
    }

    private static function rewardCPForTransfer(User $seller, User $buyer)
    {
        $cpReward = GameEconomySettings::getValue('land_transfer.cp_reward');
        $seller->addAsset('cp', $cpReward);
        $buyer->addAsset('cp', $cpReward);
    }
}
