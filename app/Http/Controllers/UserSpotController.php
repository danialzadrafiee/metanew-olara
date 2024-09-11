<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class UserSpotController extends Controller
{
    public function depositAsset(Request $request)
    {
        $validated = $this->validateRequest($request, [
            'asset_type' => 'required|in:bnb,meta',
            'amount' => 'required|numeric|min:0',
            // 'transaction_hash' => 'required|string',
        ]);

        $user = Auth::user();

        // TODO: Implement web3 validation to verify the transaction
        // if (!$this->validateTransaction($validated['transaction_hash'], $user->address, $validated['asset_type'], $validated['amount'])) {
        //     return response()->json(['error' => 'Invalid transaction'], 400);
        // }

        if ($user->addAsset($validated['asset_type'], $validated['amount'])) {
            // Log::info("User {$user->id} deposited {$validated['amount']} {$validated['asset_type']}. Transaction hash: {$validated['transaction_hash']}");
            Log::info("User {$user->id} deposited {$validated['amount']} {$validated['asset_type']}.");
            return response()->json(['message' => 'Deposit successful', 'new_balance' => $user->getAssetAttribute($validated['asset_type'])]);
        }

        // Log::error("Failed to deposit {$validated['amount']} {$validated['asset_type']} for user {$user->id}. Transaction hash: {$validated['transaction_hash']}");
        Log::error("Failed to deposit {$validated['amount']} {$validated['asset_type']} for user {$user->id}.");
        return response()->json(['error' => 'Failed to process deposit'], 500);
    }

    public function withdrawAsset(Request $request)
    {
        $validated = $this->validateRequest($request, [
            'asset_type' => 'required|in:bnb,meta',
            'amount' => 'required|numeric|min:0',
            // 'destination_address' => 'required|string',
        ]);

        $user = Auth::user();

        if (!$user->hasSufficientAsset($validated['asset_type'], $validated['amount'])) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        // TODO: Implement web3 interaction to send the asset
        // $transactionHash = $this->sendAssetOnChain($validated['asset_type'], $validated['amount'], $user->address, $validated['destination_address']);

        // if (!$transactionHash) {
        //     return response()->json(['error' => 'Failed to process withdrawal'], 500);
        // }

        if ($user->removeAsset($validated['asset_type'], $validated['amount'])) {
            // Log::info("User {$user->id} withdrew {$validated['amount']} {$validated['asset_type']} to {$validated['destination_address']}. Transaction hash: {$transactionHash}");
            Log::info("User {$user->id} withdrew {$validated['amount']} {$validated['asset_type']}.");
            return response()->json([
                'message' => 'Withdrawal successful',
                // 'transaction_hash' => $transactionHash,
                'new_balance' => $user->getAssetAttribute($validated['asset_type'])
            ]);
        }

        // Log::error("Failed to withdraw {$validated['amount']} {$validated['asset_type']} for user {$user->id} to {$validated['destination_address']}");
        Log::error("Failed to withdraw {$validated['amount']} {$validated['asset_type']} for user {$user->id}");
        return response()->json(['error' => 'Failed to process withdrawal'], 500);
    }

    private function validateRequest(Request $request, array $rules)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            response()->json(['errors' => $validator->errors()], 400)->send();
            exit;
        }
        return $validator->validated();
    }

    // private function validateTransaction($transactionHash, $userAddress, $assetType, $amount)
    // {
    //     // TODO: Implement web3 validation logic
    //     return true; // Placeholder
    // }

    // private function sendAssetOnChain($assetType, $amount, $fromAddress, $toAddress)
    // {
    //     // TODO: Implement web3 interaction to send the asset on-chain
    //     return "0x" . str_repeat("0", 64); // Placeholder transaction hash
    // }
}