<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Land;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AuctionController extends Controller
{
    public function createAuction(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'land_id' => 'required|exists:lands,id',
            'minimum_price' => 'required|numeric|min:0',
            'duration' => 'required|numeric|min:1',
        ]); 

        $land = Land::findOrFail($validatedData['land_id']);

        if ($land->owner_id !== Auth::id()) {
            return response()->json(['error' => 'You do not own this land'], 403);
        }

        if ($land->fixed_price > 0 || $land->auctions()->active()->exists()) {
            return response()->json(['error' => 'Land is not eligible for auction'], 400);
        }

        $endTime = now()->addHours($validatedData['duration']);

        $auction = new Auction([
            'land_id' => $validatedData['land_id'],
            'minimum_price' => $validatedData['minimum_price'],
            'end_time' => $endTime,
            'start_time' => now(),
            'owner_id' => $land->owner_id,
            'status' => 'active',
        ]);

        $land->auctions()->save($auction);

        return response()->json(['message' => 'Auction created successfully', 'auction' => $auction], 201);
    }


    public function placeBid(Request $request, $auctionId): JsonResponse
    {
        $validatedData = $request->validate([
            'amount' => 'required|numeric|min:0|decimal:0,8',
        ]);

        $auction = Auction::findOrFail($auctionId);
        $user = User::find(Auth::user()->id);

        if (!$auction->is_active || $auction->end_time->isPast()) {
            return response()->json(['error' => 'Auction is not active'], 400);
        }

        if ($auction->land->owner_id === $user->id) {
            return response()->json(['error' => 'You cannot bid on your own auction'], 403);
        }

        $minBidAmount = $auction->highest_bid
            ? $auction->highest_bid * 1.05
            : max($auction->minimum_price, $auction->highest_bid ?? 0);

        if ($validatedData['amount'] < $minBidAmount) {
            return response()->json(['error' => 'Bid amount is too low'], 400);
        }

        DB::beginTransaction();
        try {
            // Check if the user already has a bid on this auction
            $existingBid = $auction->bids()->where('user_id', $user->id)->orderBy('amount', 'desc')->first();

            if ($existingBid) {
                // Unlock the previous bid amount
                if (!$user->unlockAsset('bnb', $existingBid->amount)) {
                    throw new \Exception('Failed to unlock previous bid amount');
                }
            }

            // Lock the new bid amount
            if (!$user->lockAsset('bnb', $validatedData['amount'])) {
                throw new \Exception('Insufficient BNB to place bid');
            }

            $bid = new AuctionBid([
                'auction_id' => $auction->id,
                'user_id' => $user->id,
                'amount' => $validatedData['amount'],
            ]);
            $bid->save();

            $previousHighestBid = $auction->highestBid();
            if ($previousHighestBid && $previousHighestBid->user_id !== $user->id) {
                $previousHighestBid->user->unlockAsset('bnb', $previousHighestBid->amount);
            }

            DB::commit();
            return response()->json(['message' => 'Bid placed successfully', 'bid' => $bid], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($existingBid)) {
                $user->lockAsset('bnb', $existingBid->amount);
            }
            if (isset($validatedData['amount'])) {
                $user->unlockAsset('bnb', $validatedData['amount']);
            }
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function cancelAuction(Request $request, $auctionId): JsonResponse
    {
        $auction = Auction::findOrFail($auctionId);
        $user = Auth::user();

        if ($auction->owner_id !== $user->id) {
            return response()->json(['error' => 'You are not the owner of this auction'], 403);
        }

        if ($auction->status !== 'active') {
            return response()->json(['error' => 'This auction is not active'], 400);
        }

        if ($auction->bids()->count() > 0) {
            return response()->json(['error' => 'Cannot cancel auction with existing bids'], 400);
        }

        DB::beginTransaction();
        try {
            $auction->status = 'canceled';
            $auction->save();

            $land = $auction->land;
            $land->fixed_price = 0;
            $land->save();

            DB::commit();
            return response()->json(['message' => 'Auction canceled successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to cancel auction: ' . $e->getMessage()], 500);
        }
    }
}