<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\Utils;
use Web3p\EthereumTx\Transaction;
use Illuminate\Support\Facades\Log;
use Web3\Contract;
use App\Models\Web3BnbTransaction;
use App\Models\Web3MetaTransaction;

class SpotWithdrawController extends Controller
{
    private $web3;
    private $bankAddress;
    private $bankPrivateKey;
    private $contract;
    private $contractAddress;

    public function __construct()
    {
        $rpcUrl = env('RPC_URL');
        $this->bankPrivateKey = env('BANK_PVK');
        $this->bankAddress = env('BANK_ADDRESS');
        $this->contractAddress = env('META_CONTRACT_ADDRESS');

        $this->web3 = new Web3(new HttpProvider($rpcUrl));
        $this->contract = new Contract($this->web3->provider, $this->getAbi());
    }

    public function withdrawBnb(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $amount = $request->input('amount');

        $spotController = new SpotController();
        $spotController->updateBalances();

        if (!$user->hasSufficientAsset('bnb', $amount)) {
            return response()->json(['error' => 'Insufficient BNB balance'], 400);
        }

        try {
            // Attempt to send the transaction first
            $txHash = $this->sendBnbTransaction($user->address, $amount);

            // If successful, remove the asset from the user's balance
            if (!$user->removeAsset('bnb', $amount)) {
                Log::error("Failed to remove BNB from user {$user->id} account after successful transaction");
                // Consider implementing a reconciliation process here
                return response()->json(['error' => 'Transaction sent but failed to update user balance. Please contact support.'], 500);
            }

            Web3BnbTransaction::create([
                'tx_hash' => $txHash,
                'from_address' => $this->bankAddress,
                'to_address' => $user->address,
                'amount' => $amount,
                'block_number' => 0,
                'is_processed' => true
            ]);
        } catch (\Exception $e) {
            Log::error("BNB withdrawal failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to send BNB transaction: ' . $e->getMessage()], 500);
        }

        $spotController->updateBalances();
        return response()->json([
            'message' => 'BNB withdrawal successful',
            'transaction_hash' => $txHash
        ]);
    }

    public function withdrawMeta(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $amount = $request->input('amount');

        $spotController = new SpotController();
        $spotController->updateBalances();

        if (!$user->hasSufficientAsset('meta', $amount)) {
            return response()->json(['error' => 'Insufficient META balance'], 400);
        }

        try {
            // Attempt to send the transaction first
            $txHash = $this->sendMetaTokenTransaction($user->address, $amount * 10 ** 18);

            // If successful, remove the asset from the user's balance
            if (!$user->removeAsset('meta', $amount)) {
                Log::error("Failed to remove META from user {$user->id} account after successful transaction");
                // Consider implementing a reconciliation process here
                return response()->json(['error' => 'Transaction sent but failed to update user balance. Please contact support.'], 500);
            }

            Web3MetaTransaction::create([
                'tx_hash' => $txHash,
                'from_address' => $this->bankAddress,
                'to_address' => $user->address,
                'amount' => $amount,
                'block_number' => 0,
                'is_processed' => true
            ]);
        } catch (\Exception $e) {
            Log::error("META withdrawal failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to send META transaction: ' . $e->getMessage()], 500);
        }

        $spotController->updateBalances();
        return response()->json([
            'message' => 'META withdrawal successful',
            'transaction_hash' => $txHash
        ]);
    }

    private function sendBnbTransaction($toAddress, $amount)
    {
        Log::info("Starting BNB transaction", [
            'to_address' => $toAddress,
            'amount' => $amount,
            'bank_address' => $this->bankAddress
        ]);
    
        $eth = $this->web3->eth;
        $value = Utils::toWei(strval($amount), 'ether');
        $valueHex = '0x' . ltrim($value->toHex(), '0');
    
        try {
            $nonce = $this->getNonceSync($this->bankAddress);
            Log::info("Retrieved nonce", ['nonce' => $nonce]);
        } catch (\Exception $e) {
            Log::error("Failed to get nonce", ['error' => $e->getMessage()]);
            throw $e;
        }
    
        try {
            $gasPrice = $this->getGasPriceSync();
            $minGasPrice = Utils::toWei('5', 'gwei');
            $gasPrice = max($gasPrice, $minGasPrice);
            $gasPriceHex = '0x' . $gasPrice->toHex();
            Log::info("Using gas price", ['gas_price' => $gasPriceHex]);
        } catch (\Exception $e) {
            Log::error("Failed to get gas price", ['error' => $e->getMessage()]);
            throw $e;
        }
    
        $chainId = intval(env('CHAIN_ID'));
        $gasLimit = '0x' . dechex(21000);
    
        $transactionParams = [
            'nonce' => '0x' . ltrim(dechex((int)$nonce), '0'),
            'from' => $this->bankAddress,
            'to' => $toAddress,
            'gas' => $gasLimit,
            'gasPrice' => $gasPriceHex,
            'value' => $valueHex,
            'chainId' => $chainId,
            'data' => '0x'
        ];
        Log::info("Prepared transaction parameters", $transactionParams);
    
        $gasCost = Utils::toBn($gasPrice)->multiply(Utils::toBn(21000));
        $totalCost = Utils::toBn($value)->add($gasCost);
        Log::info("Transaction cost details", [
            'gas_cost' => Utils::fromWei($gasCost, 'ether'),
            'transfer_amount' => Utils::fromWei($value, 'ether'),
            'total_cost' => Utils::fromWei($totalCost, 'ether')
        ]);
    
        $minTotalCost = Utils::toWei('0.000021', 'ether');
        if ($totalCost->compare(Utils::toBn($minTotalCost)) < 0) {
            Log::error("Transaction total cost is too low", [
                'total_cost' => Utils::fromWei($totalCost, 'ether'),
                'min_required' => Utils::fromWei($minTotalCost, 'ether')
            ]);
            throw new \Exception("Transaction total cost is too low. Minimum required: " . Utils::fromWei($minTotalCost, 'ether') . " BNB");
        }
    
        try {
            $transaction = new Transaction($transactionParams);
            $signedTransaction = '0x' . $transaction->sign($this->bankPrivateKey);
            Log::info("Transaction signed successfully");
        } catch (\Exception $e) {
            Log::error("Failed to sign transaction", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    
        $txHash = null;
        $startTime = microtime(true);
        
        $eth->sendRawTransaction($signedTransaction, function ($err, $hash) use (&$txHash, $startTime) {
            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000;
            
            if ($err !== null) {
                Log::error("Error sending transaction", [
                    'error' => $err->getMessage(),
                    'duration_ms' => $duration,
                    'rpc_url' => env('RPC_URL')
                ]);
                throw new \Exception('Error sending transaction: ' . $err->getMessage());
            }
            Log::info("Transaction sent successfully", [
                'tx_hash' => $hash,
                'duration_ms' => $duration
            ]);
            $txHash = $hash;
        });
    
        return $txHash;
    }

    private function sendMetaTokenTransaction($toAddress, $amount)
    {
        $eth = $this->web3->eth;
        $data = $this->contract->at($this->contractAddress)->getData('transfer', $toAddress, $amount);
        $nonce = $this->getNonceSync($this->bankAddress);
        $gasPrice = $this->getGasPriceSync();
        $chainId = intval(env('CHAIN_ID'));

        if ($nonce !== null) {
            $transactionParams = [
                'nonce' => '0x' . ltrim(dechex((int)$nonce), '0'),
                'from' => $this->bankAddress,
                'to' => $this->contractAddress,
                'gas' => '0x' . ltrim(dechex(200000), '0'),
                'gasPrice' => $gasPrice,
                'value' => '0x0',
                'data' => '0x' . $data,
                'chainId' => $chainId,
            ];
        } else {
            Log::error("Nonce is null");
            throw new \Exception("Failed to get nonce for the transaction");
        }

        $transaction = new Transaction($transactionParams);
        $signedTransaction = '0x' . $transaction->sign($this->bankPrivateKey);

        $txHash = null;
        $eth->sendRawTransaction($signedTransaction, function ($err, $hash) use (&$txHash) {
            if ($err !== null) {
                Log::error("Error sending transaction: " . $err->getMessage());
                throw new \Exception('Error sending transaction: ' . $err->getMessage());
            }
            $txHash = $hash;
        });

        return $txHash;
    }

    private function getNonceSync($address)
    {
        $nonce = null;
        $this->web3->eth->getTransactionCount($address, 'pending', function ($err, $count) use (&$nonce) {
            if ($err !== null) {
                Log::error("Error getting nonce: " . $err->getMessage());
                throw new \Exception('Error getting nonce: ' . $err->getMessage());
            }
            $nonce = $count->toString();
        });
        return $nonce;
    }

    private function getGasPriceSync()
    {
        $gasPrice = null;
        $this->web3->eth->gasPrice(function ($err, $price) use (&$gasPrice) {
            if ($err !== null) {
                throw new \Exception('Error getting gas price: ' . $err->getMessage());
            }
            $gasPrice = $price;
        });
        return $gasPrice;
    }

    private function getAbi()
    {
        return json_decode('[
            {
                "constant": false,
                "inputs": [
                    {
                        "name": "_to",
                        "type": "address"
                    },
                    {
                        "name": "_value",
                        "type": "uint256"
                    }
                ],
                "name": "transfer",
                "outputs": [
                    {
                        "name": "",
                        "type": "bool"
                    }
                ],
                "type": "function"
            }
        ]', true);
    }
}