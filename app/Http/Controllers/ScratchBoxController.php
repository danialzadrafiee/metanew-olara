<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ScratchBox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScratchBoxController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        Log::info("User {$user->id} accessed scratch box index.");

        $availableScratchBoxes = ScratchBox::whereIn('status', ['available', 'sold'])->get();
        $ownedScratchBoxes = ScratchBox::whereIn('status', ['sold', 'opened'])
            ->whereHas('lands', function ($query) use ($user) {
                $query->where('owner_id', $user->id);
            })
            ->get();

        Log::info("Scratch box index retrieved for user {$user->id}. Available: {$availableScratchBoxes->count()}, Owned: {$ownedScratchBoxes->count()}");

        return response()->json([
            'available' => $availableScratchBoxes,
            'owned' => $ownedScratchBoxes
        ]);
    }

    public function buy($id)
    {
        $user = Auth::user();
        Log::info("User {$user->id} attempting to buy scratch box {$id}.");

        $scratchBox = ScratchBox::findOrFail($id);

        if ($scratchBox->status !== 'available') {
            Log::warning("User {$user->id} attempted to buy unavailable scratch box {$id}.");
            return response()->json(['error' => 'This scratch box is not available for purchase.'], 400);
        }

        $bnbAsset = $user->getAssetAttribute('bnb');
        if ($bnbAsset['free'] < $scratchBox->price) {
            Log::warning("User {$user->id} has insufficient BNB balance to buy scratch box {$id}. Required: {$scratchBox->price}, Available: {$bnbAsset['free']}");
            return response()->json(['error' => 'Insufficient BNB balance.'], 400);
        }

        DB::beginTransaction();
        try {
            // Deduct BNB from user
            if (!$user->removeAsset('bnb', $scratchBox->price)) {
                throw new \Exception('Failed to lock BNB for purchase.');
            }
            $scratchBox->update([
                'status' => 'sold',
                'user_id' => $user->id
            ]);
            $scratchBoxAsset = $user->assets()->firstOrCreate(['type' => 'scratch_box'], ['amount' => 0, 'locked_amount' => 0]);
            $scratchBoxAsset->increment('amount');
            DB::commit();
            Log::info("User {$user->id} successfully purchased scratch box {$id} for {$scratchBox->price} BNB.");
            return response()->json(['message' => 'Scratch box purchased successfully.', 'scratch_box' => $scratchBox]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to purchase scratch box {$id} for user {$user->id}: " . $e->getMessage());
            return response()->json(['error' => 'Failed to purchase scratch box: ' . $e->getMessage()], 500);
        }
    }



    public function open($id)
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

    public function available()
    {
        $user = Auth::user();
        Log::info("User {$user->id} requesting available scratch boxes.");

        $availableScratchBoxes = ScratchBox::whereIn('status', ['available', 'sold'])->get();

        Log::info("Retrieved {$availableScratchBoxes->count()} available scratch boxes for user {$user->id}.");
        return response()->json($availableScratchBoxes);
    }

    public function owned()
    {
        $user = Auth::user();
        Log::info("User {$user->id} requesting owned scratch boxes.");

        $ownedScratchBoxes = ScratchBox::where('user_id', $user->id)
            ->whereIn('status', ['sold', 'opened'])
            ->get();

        Log::info("Retrieved {$ownedScratchBoxes->count()} owned scratch boxes for user {$user->id}.");
        return response()->json($ownedScratchBoxes);
    }
}
