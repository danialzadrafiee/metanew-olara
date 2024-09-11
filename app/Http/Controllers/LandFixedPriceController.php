<?php

namespace App\Http\Controllers;

use App\Models\Land;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandFixedPriceController extends Controller
{
    public function setPrice(Request $request, $id): JsonResponse
    {
        $land = Land::findOrFail($id);

        if ($land->owner_id !== Auth::id()) {
            return response()->json(['error' => 'You do not own this land'], 403);
        }
        $validatedData = $request->validate([
            'price' => 'required|numeric|min:0|max:100000000000',
        ]);
        $land->fixed_price = $validatedData['price'];
        $land->save();
        return response()->json([
            'message' => 'Price set successfully',
            'land' => $land
        ]);
    }

    public function updatePrice(Request $request, $id): JsonResponse
    {
        $land = Land::findOrFail($id);

        if ($land->owner_id !== Auth::id()) {
            return response()->json(['error' => 'You do not own this land'], 403);
        }

        $validatedData = $request->validate([
            'price' => 'required|numeric|min:0|max:100000000000',
        ]);

        $land->fixed_price = $validatedData['price'];
        $land->save();

        return response()->json([
            'message' => 'Price updated successfully',
            'land' => $land
        ]);
    }

    public function cancelSell($id): JsonResponse
    {
        $land = Land::findOrFail($id);

        // Check if the authenticated user owns the land
        if ($land->owner_id !== Auth::id()) {
            return response()->json(['error' => 'You do not own this land'], 403);
        }

        $land->fixed_price = null;
        $land->save();

        return response()->json([
            'message' => 'Land removed from sale',
            'land' => $land
        ]);
    }


    public function acceptBuy(Request $request, $landId)
    {
        $land = Land::findOrFail($landId);
        $buyer = User::find(Auth::user()->id);
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
                $seller->addAsset('bnb', $land->fixed_price);
                $land->transfer(
                    $buyer->id,
                );
                $buyer->offers()->where('land_id', $land->id)->get()->each(function ($offer) {
                    $offer->delete(); 
                });
                $land->offers()->where('user_id', '!=', $buyer->id)->delete();
            });

            return response()->json([
                'message' => 'Land purchased successfully.',
                'land' => $land->fresh()->load('owner'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

}
