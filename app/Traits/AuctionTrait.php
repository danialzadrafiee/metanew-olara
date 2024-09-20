<?php

namespace App\Traits;

use App\Models\Auction;
use App\Models\LandTransfer;
use Illuminate\Support\Facades\Log;

trait AuctionTrait
{
    private function cancelAuction(Auction $auction): array
    {
        $auction->status = 'canceled';
        $auction->save();

        $canceledBids = [];
        foreach ($auction->bids as $bid) {
            $bid->status = 'canceled';
            $bid->save();

            $bidder = $bid->user;
            $unlockSuccess = $bidder->unlockAsset('bnb', $bid->amount);
            if (!$unlockSuccess) {
                Log::warning("Failed to unlock BNB for user {$bidder->id} in canceled auction {$auction->id}");
            }

            $canceledBids[] = [
                'bid_id' => $bid->id,
                'user_id' => $bidder->id,
                'amount' => $bid->amount,
                'unlock_success' => $unlockSuccess
            ];
        }

        return $canceledBids;
    }

    private function finalizeAuction(Auction $auction): array
    {
        $result = [
            'status' => 'done',
            'highest_bid' => null,
            'canceled_bids' => [],
            'ownership_transfer' => false,
            'asset_transfers' => [],
            'nft_transfer' => null
        ];
    
        $highestBid = $auction->bids()->where('status', 'active')->orderBy('amount', 'desc')->first();
    
        if ($highestBid) {
            $buyer = $highestBid->user;
            $seller = $auction->land->owner;
    
            if (!$this->isLandApprovedForTransfer($auction->land)) {
                throw new \Exception("Land is not approved for transfer.");
            }
    
            // Handle NFT transfer
            $nftResult = $this->handleLandNFT($auction->land, $buyer);
            $result['nft_transfer'] = $nftResult;
    
            if ($nftResult['txHash']) {
                $auction->land->nft_transaction_hash = $nftResult['txHash'];
                $auction->land->save();
            }
    
            // Remove locked assets from buyer
            if (!$buyer->removeLockedAsset('bnb', $highestBid->amount)) {
                throw new \Exception("Failed to remove locked BNB from buyer {$buyer->id}");
            }
    
            // Create auction transfer
            $updatedLand = LandTransfer::createAuctionTransfer(
                $auction->land,
                $seller,
                $buyer,
                $highestBid->amount
            );
    
            // Update auction and bid statuses
            $auction->status = 'done';
            $auction->save();
    
            $highestBid->status = 'accepted';
            $highestBid->save();
    
            $result['highest_bid'] = [
                'bid_id' => $highestBid->id,
                'user_id' => $buyer->id,
                'amount' => $highestBid->amount
            ];
    
            $result['ownership_transfer'] = true;
    
            // Cancel other bids
            foreach ($auction->bids as $bid) {
                if ($bid->id !== $highestBid->id) {
                    $bid->status = 'canceled';
                    $bid->save();
    
                    $bidder = $bid->user;
                    $unlockSuccess = $bidder->unlockAsset('bnb', $bid->amount);
                    if (!$unlockSuccess) {
                        Log::warning("Failed to unlock BNB for user {$bidder->id} in finalized auction {$auction->id}");
                    }
    
                    $result['canceled_bids'][] = [
                        'bid_id' => $bid->id,
                        'user_id' => $bidder->id,
                        'amount' => $bid->amount,
                        'unlock_success' => $unlockSuccess
                    ];
                }
            }
        } else {
            $auction->status = 'done';
            $auction->save();
    
            foreach ($auction->bids as $bid) {
                $bid->status = 'canceled';
                $bid->save();
    
                $result['canceled_bids'][] = [
                    'bid_id' => $bid->id,
                    'user_id' => $bid->user_id,
                    'amount' => $bid->amount
                ];
            }
        }
    
        return $result;
    }

    private function prepareExplanation(array $response): string
    {
        $explanation = "Auction execution for land ID {$response['land_id']} completed. ";

        if (!empty($response['canceled_auctions'])) {
            $canceledCount = count($response['canceled_auctions']);
            $explanation .= "{$canceledCount} older auction(s) were canceled. ";
        }

        $explanation .= "The newest auction (ID: {$response['newest_auction_id']}) was ";

        if ($response['newest_auction_status'] === 'finalized') {
            $explanation .= "finalized. ";
            if (isset($response['auction_result']['highest_bid'])) {
                $highestBid = $response['auction_result']['highest_bid'];
                $explanation .= "The highest bid of {$highestBid['amount']} BNB by user ID {$highestBid['user_id']} was accepted. ";
                $explanation .= "Land ownership was transferred to the highest bidder. ";
                $explanation .= count($response['auction_result']['canceled_bids']) . " other bid(s) were canceled and their locked assets were released. ";
            } else {
                $explanation .= "There were no valid bids, so the auction ended without a sale. ";
            }
        } else {
            $explanation .= "still active and was not finalized. ";
        }

        return $explanation;
    }
}