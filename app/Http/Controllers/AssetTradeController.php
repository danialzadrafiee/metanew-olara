<?php

namespace App\Http\Controllers;

use App\Models\AssetTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetTradeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $assets = $user->assets()->pluck('amount', 'type')->toArray();
        return response()->json($assets);
    }
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'asset_type' => 'required|string|in:gift,ticket,wood,stone,sand,gold',
            'amount' => 'required|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $asset = $user->assets()->where('type', $validatedData['asset_type'])->first();

        if (!$asset || $asset->amount < $validatedData['amount']) {
            return response()->json(['message' => 'Insufficient assets'], 400);
        }

        DB::beginTransaction();

        try {
            $trade = AssetTrade::create([
                'seller_id' => $user->id,
                'asset_type' => $validatedData['asset_type'],
                'amount' => $validatedData['amount'],
                'price' => $validatedData['price'],
            ]);

            $asset->decrement('amount', $validatedData['amount']);

            DB::commit();
            return response()->json($trade, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create trade'], 500);
        }
    }

    public function update(Request $request, AssetTrade $trade)
    {
        if ($trade->seller_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $trade->update($validatedData);

        return response()->json($trade);
    }

    public function destroy(AssetTrade $trade, Request $request)
    {
        if ($trade->seller_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            $asset = $trade->seller->assets()->where('type', $trade->asset_type)->first();
            $asset->increment('amount', $trade->amount);
            $trade->delete();

            DB::commit();
            return response()->json(['message' => 'Trade cancelled successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to cancel trade'], 500);
        }
    }

    public function buy(AssetTrade $trade, Request $request)
    {
        $buyer = $request->user();

        if ($buyer->id === $trade->seller_id) {
            return response()->json(['message' => 'You cannot buy your own trade'], 400);
        }

        $bnbAsset = $buyer->assets()->where('type', 'bnb')->first();

        if (!$bnbAsset || $bnbAsset->amount < $trade->price) {
            return response()->json(['message' => 'Insufficient BNB'], 400);
        }

        DB::beginTransaction();

        try {
            // Transfer BNB from buyer to seller
            $bnbAsset->decrement('amount', $trade->price);
            $trade->seller->assets()->where('type', 'bnb')->increment('amount', $trade->price);

            // Transfer asset from seller to buyer
            $buyerAsset = $buyer->assets()->firstOrCreate(['type' => $trade->asset_type], ['amount' => 0]);
            $buyerAsset->increment('amount', $trade->amount);

         
            // Delete the trade
            $trade->delete();

            DB::commit();
            return response()->json(['message' => 'Trade completed successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to complete trade'], 500);
        }
    }
}