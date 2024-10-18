<?php

namespace App\Http\Controllers;

use App\Models\AssetListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetListingController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 2);
        $userId = $request->user()->id;
        $type = $request->input('type', 'buy'); // 'buy' or 'own'

        $query = AssetListing::where('is_active', true);

        if ($type === 'buy') {
            $query->where('user_id', '!=', $userId);
        } else {
            $query->where('user_id', $userId);
        }

        $listings = $query->paginate($perPage);

        return response()->json([
            'listings' => $listings->items(),
            'current_page' => $listings->currentPage(),
            'total_pages' => $listings->lastPage(),
            'total' => $listings->total(),
        ]);
    }
    public function create(Request $request)
    {
        $validatedData = $request->validate([
            'asset_type' => 'required|string|in:gift,ticket,wood,stone,sand,gold',
            'amount' => 'required|integer|min:1',
            'price_in_bnb' => 'required|numeric|min:0',
        ]);
        $user = $request->user();

        DB::beginTransaction();

        try {
            if (!$user->lockAsset($validatedData['asset_type'], $validatedData['amount'])) {
                return response()->json(['error' => 'Insufficient assets'], 400);
            }

            $listing = AssetListing::create([
                'user_id' => $user->id,
                'asset_type' => $validatedData['asset_type'],
                'amount' => $validatedData['amount'],
                'price_in_bnb' => $validatedData['price_in_bnb'],
            ]);

            DB::commit();

            return response()->json(['message' => 'Listing created successfully', 'listing' => $listing]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, AssetListing $listing)
    {
        if ($listing->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'price_in_bnb' => 'required|numeric|min:0',
        ]);

        $listing->update($validatedData);

        return response()->json(['message' => 'Listing updated successfully', 'listing' => $listing]);
    }

    public function destroy(AssetListing $listing)
    {
        if ($listing->user_id !== request()->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            $user = request()->user();

            if (!$user->unlockAsset($listing->asset_type, $listing->amount)) {
                return response()->json(['error' => 'Failed to unlock asset'], 400);
            }

            $listing->delete();

            DB::commit();

            return response()->json(['message' => 'Listing removed successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function buy(AssetListing $listing)
    {
        $buyer = request()->user();

        if ($listing->user_id === $buyer->id) {
            return response()->json(['error' => 'You cannot buy your own listing'], 400);
        }

        DB::beginTransaction();

        try {
            if (!$buyer->removeAsset('bnb', $listing->price_in_bnb)) {
                return response()->json(['error' => 'Insufficient BNB balance'], 400);
            }

            $seller = $listing->user;
            if (!$seller->addAsset('bnb', $listing->price_in_bnb)) {
                return response()->json(['error' => 'Failed to transfer BNB to seller'], 400);
            }

            if (!$seller->removeLockedAsset($listing->asset_type, $listing->amount)) {
                return response()->json(['error' => 'Failed to remove locked asset from seller'], 400);
            }

            if (!$buyer->addAsset($listing->asset_type, $listing->amount)) {
                return response()->json(['error' => 'Failed to add asset to buyer'], 400);
            }

            $listing->delete();

            DB::commit();

            return response()->json(['message' => 'Asset purchased successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}