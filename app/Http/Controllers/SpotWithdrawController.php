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

        if (!$user->removeAsset('bnb', $amount)) {
            Log::error("Failed to remove BNB from user {$user->id} account");
            return response()->json(['error' => 'Failed to remove BNB from user account'], 500);
        }

        try {
            $txHash = $this->sendBnbTransaction($user->address, $amount);

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
            $user->addAsset('bnb', $amount);
            return response()->json(['error' => 'Failed to send BNB transaction: ' . $e->getMessage()], 500);
        }

        $spotController->updateBalances();
        return response()->json([
            'message' => 'BNB withdrawal successful',
            'transaction_hash' => $txHash
        ]);
    }

    private function sendBnbTransaction($toAddress, $amount)
    {
        $eth = $this->web3->eth;
        $value = Utils::toWei(strval($amount), 'ether');
        $valueHex = '0x' . ltrim($value->toHex(true), '0');
        $nonce = $this->getNonceSync($this->bankAddress);
        $gasPrice = $this->getGasPriceSync();
        $chainId = intval(env('CHAIN_ID'));

        if ($nonce !== null) {
            $transactionParams = [
                'nonce' => '0x' . ltrim(dechex($nonce), '0'),
                'from' => $this->bankAddress,
                'to' => $toAddress,
                'gas' => '0x' . ltrim(dechex(21000), '0'),
                'gasPrice' => $gasPrice,
                'value' => $valueHex,
                'chainId' => $chainId,
                'data' => '0x'
            ];
        } else {
            Log::error("Nonce is null. Cannot send transaction.");
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
            $gasPrice = '0x' . ltrim($price->toHex(true), '0');
        });
        return $gasPrice;
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

        if (!$user->removeAsset('meta', $amount)) {
            Log::error("Failed to remove META from user {$user->id} account");
            return response()->json(['error' => 'Failed to remove META from user account'], 500);
        }

        try {
            $txHash = $this->sendMetaTokenTransaction($user->address, $amount * 10 ** 18);

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
            $user->addAsset('meta', $amount);
            return response()->json(['error' => 'Failed to send META transaction: ' . $e->getMessage()], 500);
        }

        $spotController->updateBalances();
        return response()->json([
            'message' => 'META withdrawal successful',
            'transaction_hash' => $txHash
        ]);
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
                'nonce' => '0x' . ltrim(dechex($nonce), '0'),
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