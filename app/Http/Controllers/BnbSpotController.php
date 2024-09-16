<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


use SWeb3\SWeb3;


class BnbSpotController extends Controller
{






    // public function deposit(Request $request)
    // {
    //     $web3 = new SWeb3('http://localhost:8545');
    //     $validator = Validator::make($request->all(), [
    //         'amount' => 'required|numeric|min:0',
    //         'transaction_hash' => 'required|string',
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }
    //     try {
    //         $receipt = $web3->eth()->getTransactionReceipt($request->transaction_hash);
    
    //         if ($receipt !== null) {
    //             if ($receipt['status'] == '0x1') {
    //                 return response()->json(['message' => 'Deposit successful', 'receipt' => $receipt]);
    //             } else {
    //                 return response()->json(['error' => 'Transaction failed or was reverted'], 400);
    //             }
    //         } else {
    //             return response()->json(['error' => 'Transaction not found or pending'], 404);
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Error processing transaction: ' . $e->getMessage()], 500);
    //     }
    // }



    // public function deposit2(Request $request)
    // {
    //     $validated = $this->validateRequest($request, [
    //         'amount' => 'required|numeric|min:0',
    //         'transaction_hash' => 'required|string',
    //     ]);

    //     $user = Auth::user();

    //     if (!$this->validateTransaction($validated['transaction_hash'], $user->address, $validated['amount'])) {
    //         return response()->json(['error' => 'Invalid transaction'], 400);
    //     }

    //     if ($user->addAsset('bnb', $validated['amount'])) {
    //         Log::info("User {$user->id} deposited {$validated['amount']} BNB. Transaction hash: {$validated['transaction_hash']}");
    //         return response()->json(['message' => 'Deposit successful', 'new_balance' => $user->getAssetAttribute('bnb')]);
    //     }

    //     Log::error("Failed to deposit {$validated['amount']} BNB for user {$user->id}. Transaction hash: {$validated['transaction_hash']}");
    //     return response()->json(['error' => 'Failed to process deposit'], 500);
    // }

    // public function withdraw(Request $request)
    // {
    //     $validated = $this->validateRequest($request, [
    //         'amount' => 'required|numeric|min:0',
    //         'destination_address' => 'required|string',
    //     ]);

    //     $user = Auth::user();

    //     if (!$user->hasSufficientAsset('bnb', $validated['amount'])) {
    //         return response()->json(['error' => 'Insufficient balance'], 400);
    //     }

    //     $transactionHash = $this->sendBnbOnChain($validated['amount'], $user->address, $validated['destination_address']);

    //     if (!$transactionHash) {
    //         return response()->json(['error' => 'Failed to process withdrawal'], 500);
    //     }

    //     if ($user->removeAsset('bnb', $validated['amount'])) {
    //         Log::info("User {$user->id} withdrew {$validated['amount']} BNB to {$validated['destination_address']}. Transaction hash: {$transactionHash}");
    //         return response()->json([
    //             'message' => 'Withdrawal successful',
    //             'transaction_hash' => $transactionHash,
    //             'new_balance' => $user->getAssetAttribute('bnb')
    //         ]);
    //     }

    //     Log::error("Failed to withdraw {$validated['amount']} BNB for user {$user->id} to {$validated['destination_address']}");
    //     return response()->json(['error' => 'Failed to process withdrawal'], 500);
    // }

    // private function validateRequest(Request $request, array $rules)
    // {
    //     $validator = Validator::make($request->all(), $rules);
    //     if ($validator->fails()) {
    //         response()->json(['errors' => $validator->errors()], 400)->send();
    //         exit;
    //     }
    //     return $validator->validated();
    // }

    // private function validateTransaction($transactionHash, $userAddress, $amount)
    // {
    //     $web3 = new Web3('http://localhost:8545');

    //     try {
    //         $web3->eth->getTransactionReceipt($transactionHash, function ($err, $transaction) use ($userAddress, $amount) {
    //             if ($err !== null || $transaction === null) {
    //                 Log::error("Failed to retrieve transaction: " . ($err ? $err->getMessage() : "Transaction not found"));
    //                 return false;
    //             }

    //             if ($transaction->status !== '0x1') {
    //                 Log::error("Transaction failed or pending");
    //                 return false;
    //             }

    //             $expectedRecipient = '0x1dF62f291b2E969fB0849d99D9Ce41e2F137006e';
    //             if (strtolower($transaction->to) !== strtolower($expectedRecipient)) {
    //                 Log::error("Invalid recipient address");
    //                 return false;
    //             }

    //             if (strtolower($transaction->from) !== strtolower($userAddress)) {
    //                 Log::error("Transaction sender doesn't match user address");
    //                 return false;
    //             }

    //             $expectedWei = bcmul($amount, '1000000000000000000');
    //             if ($transaction->value !== $expectedWei) {
    //                 Log::error("Transaction amount doesn't match");
    //                 return false;
    //             }

    //             return true;
    //         });
    //     } catch (\Exception $e) {
    //         Log::error("Error validating transaction: " . $e->getMessage());
    //         return false;
    //     }
    // }

    // private function sendBnbOnChain($amount, $fromAddress, $toAddress)
    // {
    //     $web3 = new Web3('http://localhost:8545');

    //     try {
    //         $web3->eth->sendTransaction([
    //             'from' => $fromAddress,
    //             'to' => $toAddress,
    //             'value' => $amount * 1e18, // Convert to Wei
    //             'gas' => '21000',
    //             'gasPrice' => $web3->eth->gasPrice(),
    //         ], function ($err, $transaction) use (&$transactionHash) {
    //             if ($err !== null) {
    //                 Log::error("Failed to send BNB: " . $err->getMessage());
    //                 return null;
    //             }
    //             $transactionHash = $transaction;
    //         });

    //         return $transactionHash;
    //     } catch (\Exception $e) {
    //         Log::error("Error sending BNB on-chain: " . $e->getMessage());
    //         return null;
    //     }
    // }
}
