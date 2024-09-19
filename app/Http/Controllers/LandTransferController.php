<?php

namespace App\Http\Controllers;

use App\Models\Land;
use App\Models\LandTransfer;
use App\Models\User;
use App\Models\Offer;
use App\Traits\LandNFTTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LandTransferController extends Controller
{
    use LandNFTTrait;

    protected $nftController;

    public function __construct(NftController $nftController)
    {
        $this->nftController = $nftController;
    }

    public function acceptBuy($landId): JsonResponse
    {
        try {
            $land = Land::findOrFail($landId);
            $buyer = Auth::user();
            $seller = User::findOrFail($land->owner_id);

            if ($land->is_suspend) {
                return response()->json(['message' => 'This land is not for sale.'], 400);
            }

            if (!$this->isLandApprovedForTransfer($land)) {
                $land->update(['fixed_price' => 0]);
                return response()->json(['error' => 'This land is not approved for transfer. The sale has been canceled.'], 400);
            }

            if (!$buyer->hasSufficientAsset('bnb', $land->fixed_price)) {
                return response()->json(['error' => 'Insufficient funds to purchase this land.'], 400);
            }

            $result = $this->handleLandNFT($land, $buyer);
            if ($result['txHash']) {
                $land->nft_transaction_hash = $result['txHash'];
            }

            $updatedLand = LandTransfer::createTransfer(
                $land,
                $seller,
                $buyer,
                'fixed_price',
                'bnb',
                $land->fixed_price
            );

            return response()->json([
                'message' => 'Land purchased successfully.',
                'land' => $updatedLand,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function acceptOffer($offerId): JsonResponse
    {
        try {
            $offer = Offer::findOrFail($offerId);
            $land = $offer->land;
            $buyer = $offer->user;
            $seller = $land->owner;

            if (!$this->isLandApprovedForTransfer($land)) {
                return response()->json(['error' => 'This land is not approved for transfer.'], 400);
            }

            DB::transaction(function () use ($offer, $land, $buyer, $seller) {
                $buyer->removeAsset($offer->price_asset_type, $offer->price);
                
                $result = $this->handleLandNFT($land, $buyer);
                if ($result['txHash']) {
                    $land->nft_transaction_hash = $result['txHash'];
                }

                LandTransfer::createTransfer(
                    $land,
                    $seller,
                    $buyer,
                    'offer',
                    $offer->price_asset_type,
                    $offer->price,
                    $offer
                );

                $land->increment('transfer_times');
            });

            return response()->json([
                'message' => 'Offer accepted successfully.',
                'land' => $land->fresh()->load('owner'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing the offer: ' . $e->getMessage()], 500);
        }
    }
}