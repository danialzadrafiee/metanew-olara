<?php

namespace App\Traits;

use App\Models\Land;
use App\Models\User;
use Illuminate\Support\Facades\Log;

trait ScratchBoxTrait
{
    protected function processLand(Land $land, User $user): array
    {
        try {
            $tokenId = $land->id;
            $currentOwner = $this->nftController->getTokenOwner($tokenId);
            $bankAddress = "0x24015B83f9B2CD8BF831101e79b3BFB9aE20afa1";

            if ($currentOwner !== null && strtolower($currentOwner) !== strtolower($bankAddress)) {
                throw new \Exception("Land {$land->id} is not owned by the bank.");
            }

            if ($currentOwner !== null) {
                $txHash = $this->nftController->transferFrom($bankAddress, $user->address, $tokenId);
                $land->nft_transaction_hash = $txHash;
            }

            $land->update([
                'owner_id' => $user->id,
                'fixed_price' => 0
            ]);

            return ['success' => true, 'land' => $land->fresh()];
        } catch (\Exception $e) {
            Log::error("Failed to process land {$land->id}: " . $e->getMessage());
            return ['success' => false, 'land' => $land];
        }
    }

    protected function processLands($lands, User $user): array
    {
        $transferredLands = [];
        $failedLands = [];
        $refundAmount = 0;

        foreach ($lands as $land) {
            $result = $this->processLand($land, $user);
            if ($result['success']) {
                $transferredLands[] = $result['land'];
            } else {
                $failedLands[] = $result['land'];
                $refundAmount += $result['land']->fixed_price;
            }
        }

        return [
            'transferred' => $transferredLands,
            'failed' => $failedLands,
            'refundAmount' => $refundAmount
        ];
    }
}
