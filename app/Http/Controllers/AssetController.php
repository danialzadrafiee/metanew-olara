<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function getBalance($userId, $type)
    {
        $user = User::findOrFail($userId);
        $asset = $user->getAssetAttribute($type);

        if (!$asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        return response()->json($asset);
    }

    public function lock(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:cp,meta,bnb,iron,wood,sand,gold,giftbox,ticket,chest_silver,chest_gold,chest_diamond,scratch_box',
            'amount' => 'required|min:0|integer',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::findOrFail($request->user_id);
            
            if (!$user->lockAsset($request->type, $request->amount)) {
                return response()->json(['message' => 'Insufficient funds'], 400);
            }

            return response()->json($user->getAssetAttribute($request->type));
        });
    }

    public function unlock(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:cp,meta,bnb,iron,wood,sand,gold,giftbox,ticket,chest_silver,chest_gold,chest_diamond,scratch_box',
            'amount' => 'required|min:0|integer',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::findOrFail($request->user_id);
            
            if (!$user->unlockAsset($request->type, $request->amount)) {
                return response()->json(['message' => 'Invalid unlock amount'], 400);
            }

            return response()->json($user->getAssetAttribute($request->type));
        });
    }

    public function add(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:cp,meta,bnb,iron,wood,sand,gold,giftbox,ticket,chest_silver,chest_gold,chest_diamond,scratch_box',
            'amount' => 'required|min:0|integer',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::findOrFail($request->user_id);
            $user->addAsset($request->type, $request->amount);

            return response()->json($user->getAssetAttribute($request->type));
        });
    }

    public function subtract(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:cp,meta,bnb,iron,wood,sand,gold,giftbox,ticket,chest_silver,chest_gold,chest_diamond,scratch_box',
            'amount' => 'required|min:0|integer',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::findOrFail($request->user_id);
            
            if (!$user->subtractAsset($request->type, $request->amount)) {
                return response()->json(['message' => 'Insufficient funds'], 400);
            }

            return response()->json($user->getAssetAttribute($request->type));
        });
    }
}