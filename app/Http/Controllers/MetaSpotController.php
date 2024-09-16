<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Web3\Web3;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

class MetaSpotController extends Controller
{
    public function deposit(Request $request)
    {
        $validated = $this->validateRequest($request, [
            'amount' => 'required|numeric|min:0',
            'transaction_hash' => 'required|string',
        ]);

        $user = Auth::user();

        if (!$this->validateTransaction($validated['transaction_hash'], $user->address, $validated['amount'])) {
            return response()->json(['error' => 'Invalid transaction'], 400);
        }

        if ($user->addAsset('meta', $validated['amount'])) {
            Log::info("User {$user->id} deposited {$validated['amount']} META. Transaction hash: {$validated['transaction_hash']}");
            return response()->json(['message' => 'Deposit successful', 'new_balance' => $user->getAssetAttribute('meta')]);
        }

        Log::error("Failed to deposit {$validated['amount']} META for user {$user->id}. Transaction hash: {$validated['transaction_hash']}");
        return response()->json(['error' => 'Failed to process deposit'], 500);
    }

    public function withdraw(Request $request)
    {
        $validated = $this->validateRequest($request, [
            'amount' => 'required|numeric|min:0',
            'destination_address' => 'required|string',
        ]);

        $user = Auth::user();

        if (!$user->hasSufficientAsset('meta', $validated['amount'])) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        $transactionHash = $this->sendMetaOnChain($validated['amount'], $user->address, $validated['destination_address']);

        if (!$transactionHash) {
            return response()->json(['error' => 'Failed to process withdrawal'], 500);
        }

        if ($user->removeAsset('meta', $validated['amount'])) {
            Log::info("User {$user->id} withdrew {$validated['amount']} META to {$validated['destination_address']}. Transaction hash: {$transactionHash}");
            return response()->json([
                'message' => 'Withdrawal successful',
                'transaction_hash' => $transactionHash,
                'new_balance' => $user->getAssetAttribute('meta')
            ]);
        }

        Log::error("Failed to withdraw {$validated['amount']} META for user {$user->id} to {$validated['destination_address']}");
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

    private function validateTransaction($transactionHash, $userAddress, $amount)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('ETHEREUM_NODE_URL'))));

        try {
            $web3->eth->getTransactionReceipt($transactionHash, function ($err, $transaction) use ($userAddress, $amount) {
                if ($err !== null || $transaction === null) {
                    Log::error("Failed to retrieve transaction: " . ($err ? $err->getMessage() : "Transaction not found"));
                    return false;
                }

                if ($transaction->status !== '0x1') {
                    Log::error("Transaction failed or pending");
                    return false;
                }

                $expectedRecipient = env('META_DEPOSIT_ADDRESS');
                if (strtolower($transaction->to) !== strtolower($expectedRecipient)) {
                    Log::error("Invalid recipient address");
                    return false;
                }

                if (strtolower($transaction->from) !== strtolower($userAddress)) {
                    Log::error("Transaction sender doesn't match user address");
                    return false;
                }

                $inputData = $transaction->input;
                $methodId = substr($inputData, 0, 10);
                $expectedMethodId = '0xa9059cbb'; // transfer method ID

                if ($methodId !== $expectedMethodId) {
                    Log::error("Invalid method ID for META token transfer");
                    return false;
                }

                $tokenAmount = hexdec(substr($inputData, -64));
                $expectedTokenAmount = $amount * (10 ** 18); // Assuming 18 decimals for META token
                if ($tokenAmount !== $expectedTokenAmount) {
                    Log::error("Token amount doesn't match");
                    return false;
                }

                return true;
            });
        } catch (\Exception $e) {
            Log::error("Error validating transaction: " . $e->getMessage());
            return false;
        }
    }

    private function sendMetaOnChain($amount, $fromAddress, $toAddress)
    {
        $web3 = new Web3(new HttpProvider(new HttpRequestManager(env('ETHEREUM_NODE_URL'))));

        try {
            $contractABI = json_decode(file_get_contents(storage_path('app/MetaTokenABI.json')), true);
            $contractAddress = env('META_TOKEN_CONTRACT_ADDRESS');
            $contract = new Contract($web3->provider, $contractABI);

            $contract->at($contractAddress)->send('transfer', $toAddress, $amount * 1e18, [
                'from' => $fromAddress,
                'gas' => '60000',
                'gasPrice' => $web3->eth->gasPrice(),
            ], function ($err, $transaction) use (&$transactionHash) {
                if ($err !== null) {
                    Log::error("Failed to send META token: " . $err->getMessage());
                    return null;
                }
                $transactionHash = $transaction;
            });

            return $transactionHash;
        } catch (\Exception $e) {
            Log::error("Error sending META token on-chain: " . $e->getMessage());
            return null;
        }
    }
}