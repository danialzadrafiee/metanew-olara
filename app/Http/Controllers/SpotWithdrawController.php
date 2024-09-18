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
    private $fromAddress = '0x24015B83f9B2CD8BF831101e79b3BFB9aE20afa1';
    private $privateKey = '0x6ede5877c85dfb5c94d78ab2271bfa8fe3782d6c548470f948b7b17b698809cd';

    private $contract;
    private $contractAddress = '0x523c2d041153D299602468684bfeC9a7e448eDB0';

    public function __construct()
    {
        $this->web3 = new Web3(new HttpProvider('http://127.0.0.1:8545'));
        $this->contract = new Contract($this->web3->provider, $this->getAbi());
    }

    public function withdrawBnb(Request $request)
    {
        // Validate the request
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $amount = $request->input('amount');

        // Step 1: Update balance
        $spotController = new SpotController();
        $spotController->updateBalances();

        // Step 2: Check if user has sufficient balance and remove asset
        if (!$user->hasSufficientAsset('bnb', $amount)) {
            return response()->json(['error' => 'Insufficient BNB balance'], 400);
        }

        if (!$user->removeAsset('bnb', $amount)) {
            return response()->json(['error' => 'Failed to remove BNB from user account'], 500);
        }

        // Step 3: Send BNB transaction
        try {
            $txHash = $this->sendBnbTransaction($user->address, $amount);

            // Step 4: Create and mark the transaction as processed
            Web3BnbTransaction::create([
                'tx_hash' => $txHash,
                'from_address' => $this->fromAddress,
                'to_address' => $user->address,
                'amount' => $amount,
                'block_number' => 0, // You might want to fetch the actual block number
                'is_processed' => true
            ]);

        } catch (\Exception $e) {
            // If transaction fails, revert the asset removal
            $user->addAsset('bnb', $amount);
            Log::error('BNB withdrawal failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send BNB transaction'], 500);
        }

        // Step 5: Update balance again
        $spotController->updateBalances();

        return response()->json([
            'message' => 'BNB withdrawal successful',
            'transaction_hash' => $txHash
        ]);
    }

    public function withdrawMeta(Request $request)
    {
        // Validate the request
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $amount = $request->input('amount');

        // Step 1: Update balance
        $spotController = new SpotController();
        $spotController->updateBalances();

        // Step 2: Check if user has sufficient balance and remove asset
        if (!$user->hasSufficientAsset('meta', $amount)) {
            return response()->json(['error' => 'Insufficient META balance'], 400);
        }

        if (!$user->removeAsset('meta', $amount)) {
            return response()->json(['error' => 'Failed to remove META from user account'], 500);
        }

        // Step 3: Send META transaction
        try {
            $txHash = $this->sendMetaTokenTransaction($user->address, $amount * 100000000); // Convert to smallest unit (8 decimal places)

            // Step 4: Create and mark the transaction as processed
            Web3MetaTransaction::create([
                'tx_hash' => $txHash,
                'from_address' => $this->fromAddress,
                'to_address' => $user->address,
                'amount' => $amount,
                'block_number' => 0, // You might want to fetch the actual block number
                'is_processed' => true
            ]);

        } catch (\Exception $e) {
            // If transaction fails, revert the asset removal
            $user->addAsset('meta', $amount);
            Log::error('META withdrawal failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send META transaction'], 500);
        }

        // Step 5: Update balance again
        $spotController->updateBalances();

        return response()->json([
            'message' => 'META withdrawal successful',
            'transaction_hash' => $txHash
        ]);
    }

    private function sendBnbTransaction($toAddress, $amount)
    {
        $eth = $this->web3->eth;
        $value = Utils::toWei(strval($amount), 'ether');

        $transactionParams = [
            'from' => $this->fromAddress,
            'to' => $toAddress,
            'gas' => '0x' . dechex(21000),
            'value' => '0x' . $value->toHex(true),
            'chainId' => 1337,
            'data' => ''
        ];

        // Get nonce
        $eth->getTransactionCount($this->fromAddress, 'pending', function ($err, $nonce) use (&$transactionParams) {
            if ($err !== null) {
                throw new \Exception('Error getting nonce: ' . $err->getMessage());
            }
            $transactionParams['nonce'] = '0x' . $nonce->toHex(true);
        });

        // Get gas price
        $eth->gasPrice(function ($err, $gasPrice) use (&$transactionParams) {
            if ($err !== null) {
                throw new \Exception('Error getting gas price: ' . $err->getMessage());
            }
            $transactionParams['gasPrice'] = '0x' . $gasPrice->toHex(true);
        });

        $transaction = new Transaction($transactionParams);
        $signedTransaction = '0x' . $transaction->sign(trim($this->privateKey, '0x'));

        $txHash = null;
        $eth->sendRawTransaction($signedTransaction, function ($err, $hash) use (&$txHash) {
            if ($err !== null) {
                throw new \Exception('Error sending transaction: ' . $err->getMessage());
            }
            $txHash = $hash;
        });

        return $txHash;
    }

    private function sendMetaTokenTransaction($toAddress, $amount)
    {
        $eth = $this->web3->eth;

        $data = $this->contract->at($this->contractAddress)->getData('transfer', $toAddress, $amount);

        $transactionParams = [
            'from' => $this->fromAddress,
            'to' => $this->contractAddress,
            'gas' => '0x' . dechex(200000), // Adjust gas limit as needed
            'value' => '0x0',
            'data' => '0x' . $data,
            'chainId' => 1337 // Adjust chain ID as needed
        ];

        // Get nonce
        $eth->getTransactionCount($this->fromAddress, 'pending', function ($err, $nonce) use (&$transactionParams) {
            if ($err !== null) {
                throw new \Exception('Error getting nonce: ' . $err->getMessage());
            }
            $transactionParams['nonce'] = '0x' . $nonce->toHex(true);
        });

        // Get gas price
        $eth->gasPrice(function ($err, $gasPrice) use (&$transactionParams) {
            if ($err !== null) {
                throw new \Exception('Error getting gas price: ' . $err->getMessage());
            }
            $transactionParams['gasPrice'] = '0x' . $gasPrice->toHex(true);
        });

        $transaction = new Transaction($transactionParams);
        $signedTransaction = '0x' . $transaction->sign(trim($this->privateKey, '0x'));

        $txHash = null;
        $eth->sendRawTransaction($signedTransaction, function ($err, $hash) use (&$txHash) {
            if ($err !== null) {
                throw new \Exception('Error sending transaction: ' . $err->getMessage());
            }
            $txHash = $hash;
        });

        return $txHash;
    }

    private function getAbi()
    {
        // Return the ABI for the META token contract
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