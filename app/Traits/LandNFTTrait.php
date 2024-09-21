<?php

namespace App\Traits;

use App\Models\Land;
use App\Models\User;
use Log;

trait LandNFTTrait
{
    public function isLandApprovedForTransfer(Land $land): bool
    {
        $tokenId = $land->id;
        $approvedAddress = $this->nftController->getApproved($tokenId);
        $bankAddress = env('BANK_ADDRESS');
        $owner = $land->owner;
        $isOwnerBank = $owner->role === 'bank';
        $isApprovedByBank = $approvedAddress !== null && strtolower($approvedAddress) === strtolower($bankAddress);
        $result = $isOwnerBank || $isApprovedByBank;
        return $result;
    }


    public function handleLandNFT(Land $land, User $buyer)
    {
        $tokenId = $land->id;
        $currentOwner = $this->nftController->getTokenOwner($tokenId);
        $approvedAddress = $this->nftController->getApproved($tokenId);
        $bankAddress = env('BANK_ADDRESS');
        if ($currentOwner === null || $approvedAddress === null) {
            $txHash = $this->nftController->mintNFT($buyer->address, $tokenId, "https://ipfs.io/ipfs/bafybeibulyuw4qmptj3z4kujjh2w5677xx3eizwydz2yao3nnrt7fhsnlq/1000.json");
            return ['action' => 'minted', 'txHash' => $txHash];
        } elseif (strtolower($currentOwner) === strtolower($buyer->address)) {
            $this->updateLandOwnership($land, $buyer->id);
            return ['action' => 'database_updated', 'txHash' => null];
        } elseif (strtolower($approvedAddress) === strtolower($bankAddress)) {
            $txHash = $this->nftController->transferFrom($currentOwner, $buyer->address, $tokenId);
            return ['action' => 'transferred', 'txHash' => $txHash];
        } else {
            $blockchainOwner = User::whereRaw('LOWER(address) = ?', [strtolower($currentOwner)])->first();
            if ($blockchainOwner) {
                $this->updateLandOwnership($land, $blockchainOwner->id);
                return ['action' => 'database_updated', 'txHash' => null];
            } else {
                throw new \Exception("Blockchain owner not found in database for land {$land->id}");
            }
        }
    }

    private function updateLandOwnership(Land $land, int $newOwnerId)
    {
        $land->update(['owner_id' => $newOwnerId, 'fixed_price' => 0]);
    }
}
