<?php

namespace App\Http\Controllers;

use App\Models\Land;
use App\Models\LandTransfer;
use App\Models\User;
use App\Models\Offer;
use App\Models\Auction;
use App\Models\ScratchBox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Log;

class LandTransferController extends Controller
{
    public function acceptBuy(Request $request, $landId): JsonResponse
    {
        $land = Land::findOrFail($landId);
        $buyer = Auth::user();
        $seller = User::findOrFail($land->owner_id);

        if ($land->is_suspend) {
            return response()->json(['message' => 'This land is not for sale.'], 400);
        }

        if ($land->owner_id === $buyer->id) {
            return response()->json(['message' => 'You cannot buy your own land.'], 400);
        }

        if (!$buyer->hasSufficientAsset('bnb', $land->fixed_price)) {
            return response()->json(['message' => 'Insufficient BNB to purchase this land.'], 400);
        }

        try {
            DB::transaction(function () use ($land, $buyer, $seller) {
                $buyer->removeAsset('bnb', $land->fixed_price);
                $updatedLand = LandTransfer::createTransfer(
                    $land,
                    $seller,
                    $buyer,
                    'fixed_price',
                    'bnb',
                    $land->fixed_price
                );
                $buyer->offers()->where('land_id', $land->id)->delete();
                $land->offers()->where('user_id', '!=', $buyer->id)->delete();
            });

            return response()->json([
                'message' => 'Land purchased successfully.',
                'land' => $land->fresh()->load('owner'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in acceptBuy: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while processing the purchase.'], 500);
        }
    }

    public function acceptOffer($offerId): JsonResponse
    {
        try {
            $offer = Offer::findOrFail($offerId);
            $land = $offer->land;
            $buyer = $offer->user;
            $seller = $land->owner;

            DB::transaction(function () use ($offer, $land, $buyer, $seller) {
                $buyer->removeAsset($offer->asset_type, $offer->amount);
                $updatedLand = LandTransfer::createTransfer(
                    $land,
                    $seller,
                    $buyer,
                    'offer',
                    $offer->asset_type,
                    $offer->amount
                );
                $land->offers()->delete();
                $offer->delete();
            });

            return response()->json([
                'message' => 'Offer accepted successfully.',
                'land' => $land->fresh()->load('owner'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in acceptOffer: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while processing the offer.'], 500);
        }
    }

    public function processAuction($auctionId): JsonResponse
    {
        try {
            $auction = Auction::findOrFail($auctionId);
            $result = $auction->processAuction();

            if ($result) {
                $land = $auction->land;
                $buyer = $auction->highestBid->user;
                $seller = $land->owner;

                $updatedLand = LandTransfer::createTransfer(
                    $land,
                    $seller,
                    $buyer,
                    'auction',
                    $auction->asset_type,
                    $auction->highestBid->amount
                );

                return response()->json([
                    'message' => 'Auction processed successfully.',
                    'land' => $updatedLand,
                ], 200);
            } else {
                return response()->json(['message' => 'Auction ended with no valid bids.'], 200);
            }
        } catch (\Exception $e) {
            Log::error('Error in processAuction: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while processing the auction.'], 500);
        }
    }

    public function openScratchBox($id)
    {
        $user = Auth::user();
        Log::info("User {$user->id} attempting to open scratch box {$id}.");

        $scratchBox = ScratchBox::findOrFail($id);

        if ($scratchBox->status !== 'sold') {
            Log::warning("User {$user->id} attempted to open unavailable scratch box {$id}.");
            return response()->json(['error' => 'This scratch box is not available for opening.'], 400);
        }

        DB::beginTransaction();
        try {
            $scratchBoxAsset = $user->assets()->where('type', 'scratch_box')->lockForUpdate()->first();
            if (!$scratchBoxAsset || $scratchBoxAsset->amount <= 0) {
                throw new \Exception('There are no scratch boxes available to open.');
            }
            $lands = $scratchBox->open($user);
            $scratchBoxAsset->amount -= 1;
            $scratchBoxAsset->save();
            DB::commit();
            Log::info("User {$user->id} successfully opened scratch box {$id}. Lands received: " . count($lands));
            return response()->json([
                'message' => 'Scratch box opened successfully.',
                'lands' => $lands
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to open scratch box {$id} for user {$user->id}: " . $e->getMessage());
            return response()->json(['error' => 'Failed to open scratch box: ' . $e->getMessage()], 500);
        }
    }
}