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
    private function validateOwnership(Land $land): ?JsonResponse
    {
        if ($land->owner_id !== Auth::id()) {
            return response()->json(['error' => 'You do not own this land'], 403);
        }
        return null;
    }

    private function validatePriceInput(Request $request): array
    {
        return $request->validate([
            'price' => 'required|numeric|min:0|max:100000000000',
        ]);
    }

    private function updateLandPrice(Land $land, float $price): void
    {
        $land->fixed_price = $price;
        $land->save();
    }

    public function setPrice(Request $request, $id): JsonResponse
    {
        $land = Land::findOrFail($id);
        if ($response = $this->validateOwnership($land)) {
            return $response;
        }

        $validatedData = $this->validatePriceInput($request);
        $this->updateLandPrice($land, $validatedData['price']);

        return response()->json([
            'message' => 'Price set successfully',
            'land' => $land
        ]);
    }

    public function updatePrice(Request $request, $id): JsonResponse
    {
        return $this->setPrice($request, $id);
    }

    public function cancelSell($id): JsonResponse
    {
        $land = Land::findOrFail($id);
        
        if ($response = $this->validateOwnership($land)) {
            return $response;
        }

        $this->updateLandPrice($land, 0);

        return response()->json([
            'message' => 'Land removed from sale',
            'land' => $land
        ]);
    }

    public function acceptBuy(Request $request, $landId): JsonResponse
    {
        $land = Land::findOrFail($landId);
        $buyer = Auth::user();
        $seller = User::findOrFail($land->owner_id);

        if ($this->validatePurchase($land, $buyer)) {
            return $this->validatePurchase($land, $buyer);
        }

        try {
            DB::transaction(function () use ($land, $buyer, $seller) {
                $this->processPurchase($land, $buyer, $seller);
            });

            return response()->json([
                'message' => 'Land purchased successfully.',
                'land' => $land->fresh()->load('owner'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function validatePurchase(Land $land, User $buyer): ?JsonResponse
    {
        if ($land->is_suspend) {
            return response()->json(['message' => 'This land is not for sale.'], 400);
        }

        if ($land->owner_id === $buyer->id) {
            return response()->json(['message' => 'You cannot buy your own land.'], 400);
        }

        if (!$buyer->hasSufficientAsset('bnb', $land->fixed_price)) {
            return response()->json(['message' => 'Insufficient BNB to purchase this land.'], 400);
        }
        return null;
    }

    private function processPurchase(Land $land, User $buyer, User $seller): void
    {
        $buyer->removeAsset('bnb', $land->fixed_price);
        $seller->addAsset('bnb', $land->fixed_price);
        $land->transfer($buyer->id);
        $this->cleanupOffers($land, $buyer);
        $land->fixed_price = null;
        $land->save();
    }

    private function cleanupOffers(Land $land, User $buyer): void
    {
        $buyer->offers()->where('land_id', $land->id)->delete();
        $land->offers()->where('user_id', '!=', $buyer->id)->delete();
    }
}