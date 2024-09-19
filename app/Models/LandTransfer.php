<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public static function createTransfer(Land $land, User $seller, User $buyer, string $transferType, string $assetType, float $assetAmount, ?Offer $offer = null)
    {
        return DB::transaction(function () use ($land, $seller, $buyer, $transferType, $assetType, $assetAmount, $offer) {
            $land->increment('transfer_times');
            $shares = self::calculateShares($assetAmount, $seller, $land->transfer_times);

            $transfer = self::create([
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
            self::distributeShares($shares, $assetType, $seller);
            self::updateLandOwnership($land, $buyer->id);
            self::rewardCPForTransfer($seller, $buyer);

            if ($offer) {
                $land->offers()->delete();
                $offer->delete();
            }

            return $land->fresh()->load('owner');
        });
    }

    private static function removeBuyerAssets(User $buyer, string $transferType, string $assetType, float $assetAmount)
    {
        $method = $transferType === 'offer' ? 'removeLockedAsset' : 'removeAsset';
        if (!$buyer->$method($assetType, $assetAmount)) {
            throw new \Exception("Failed to remove {$assetType} from buyer.");
        }
    }

    private static function distributeShares($shares, $assetType, User $seller)
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

    private static function calculateShares(float $assetAmount, User $seller, int $transferTimes)
    {
        if ($transferTimes === 1) {
            $foundationShare = GameEconomySettings::getValue('first_transfer.foundation_share') * $assetAmount;
            $bankShare = $seller->inviter_id
                ? GameEconomySettings::getValue('first_transfer.bank_share') * $assetAmount
                : GameEconomySettings::getValue('first_transfer.bank_share_no_inviter') * $assetAmount;
            $inviterShare = $seller->inviter_id
                ? GameEconomySettings::getValue('first_transfer.inviter_share') * $assetAmount
                : 0;
            $receiverShare = 0;
        } else {
            $sellerShare = GameEconomySettings::getValue('subsequent_transfer.seller_share');
            $bankShare = GameEconomySettings::getValue('subsequent_transfer.bank_share') * $assetAmount;
            $foundationShare = GameEconomySettings::getValue('subsequent_transfer.foundation_share') * $assetAmount;
            $receiverShare = $sellerShare * $assetAmount;
            $inviterShare = 0;
        }

        return [
            'receiver' => $receiverShare,
            'bank' => $bankShare,
            'foundation' => $foundationShare,
            'inviter' => $inviterShare,
            'seller_id' => $seller->id,
            'inviter_id' => $seller->inviter_id,
        ];
    }
}