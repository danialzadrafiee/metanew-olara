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

    public static function createTransfer(Land $land, User $seller, User $buyer, string $transferType, string $assetType, float $assetAmount)
    {
        return DB::transaction(function () use ($land, $seller, $buyer, $transferType, $assetType, $assetAmount) {
            $land->increment('transfer_times');
            $transferTimes = $land->transfer_times;
            $shares = self::calculateShares($assetAmount, $seller, $transferTimes);

            $transfer = self::create([
                'land_id' => $land->id,
                'seller_user_id' => $seller->id,
                'buyer_user_id' => $buyer->id,
                'transfer_type' => $transferType,
                'asset_type' => $assetType,
                'asset_amount' => $assetAmount,
                'land_transfer_times' => $transferTimes,
                'receiver_share_amount' => $shares['receiver'],
                'bank_share_amount' => $shares['bank'],
                'foundation_share_amount' => $shares['foundation'],
                'inviter_share_amount' => $shares['inviter'],
            ]);

            $transfer->distributeAssets($shares);
            $transfer->updateLandOwnership($land, $buyer->id);
            $transfer->rewardCPForTransfer($seller, $buyer);

            return $land->fresh()->load('owner');
        });
    }

    private static function calculateShares(float $assetAmount, User $seller, int $transferTimes)
    {
        \Log::debug("Calculate Shares - Asset Amount: " . $assetAmount . ", Transfer Times: " . $transferTimes);

        if ($transferTimes === 1) {  // First transfer
            $foundationShare = GameEconomySettings::getValue('first_transfer.foundation_share') * $assetAmount;
            \Log::debug("Foundation Share: " . $foundationShare);

            if ($seller->inviter_id) {
                $bankShare = GameEconomySettings::getValue('first_transfer.bank_share') * $assetAmount;
                $inviterShare = GameEconomySettings::getValue('first_transfer.inviter_share') * $assetAmount;
                \Log::debug("Bank Share: " . $bankShare . ", Inviter Share: " . $inviterShare);
            } else {
                $bankShare = GameEconomySettings::getValue('first_transfer.bank_share_no_inviter') * $assetAmount;
                $inviterShare = 0;
                \Log::debug("Bank Share (No Inviter): " . $bankShare);
            }

            $receiverShare = 0;  // The original owner (bank) doesn't receive a share
        } else {  // Subsequent transfers
            $sellerShare = GameEconomySettings::getValue('subsequent_transfer.seller_share');
            $bankShare = GameEconomySettings::getValue('subsequent_transfer.bank_share') * $assetAmount;
            $foundationShare = GameEconomySettings::getValue('subsequent_transfer.foundation_share') * $assetAmount;
            $receiverShare = $sellerShare * $assetAmount;
            $inviterShare = 0;  // No inviter share for subsequent transfers
            \Log::debug("Seller Share: " . $sellerShare . ", Bank Share: " . $bankShare . ", Foundation Share: " . $foundationShare . ", Receiver Share: " . $receiverShare);
        }

        $result = [
            'receiver' => $receiverShare,
            'bank' => $bankShare,
            'foundation' => $foundationShare,
            'inviter' => $inviterShare,
            'seller_id' => $seller->id,
            'inviter_id' => $seller->inviter_id,
        ];

        \Log::debug("Final Shares: " . json_encode($result));

        return $result;
    }

    private function rewardCPForTransfer(User $seller, User $buyer)
    {
        $cpReward = GameEconomySettings::getValue('land_transfer.cp_reward');
        $seller->addAsset('cp', $cpReward);
        $buyer->addAsset('cp', $cpReward);
    }

    private function distributeAssets(array $shares)
    {
        $seller = User::findOrFail($shares['seller_id']);
        $bank = User::findOrFail(1);
        $foundation = User::findOrFail(2);

        $seller->addAsset($this->asset_type, $shares['receiver']);
        $bank->addAsset($this->asset_type, $shares['bank']);
        $foundation->addAsset($this->asset_type, $shares['foundation']);

        if ($shares['inviter_id']) {
            $inviter = User::findOrFail($shares['inviter_id']);
            $inviter->addAsset($this->asset_type, $shares['inviter']);
        }
    }

    private function updateLandOwnership(Land $land, int $newOwnerId)
    {
        $land->update([
            'owner_id' => $newOwnerId,
            'fixed_price' => 0,
        ]);
    }
}
