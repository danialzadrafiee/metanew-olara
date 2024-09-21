<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Web3BnbTransaction;
use App\Models\Web3MetaTransaction;
use Illuminate\Support\Facades\Log;

class SpotController extends Controller
{
    private $bankAddress;

    public function __construct()
    {
        $this->bankAddress = env('BANK_ADDRESS');
    }

    public function processNewTransactions()
    {
        $processedBnb = $this->processNewBnbTransactions();
        $processedMeta = $this->processNewMetaTransactions();

        return [
            'processed_bnb' => $processedBnb,
            'processed_meta' => $processedMeta
        ];
    }

    private function processNewBnbTransactions()
    {
        $unprocessedTransactions = Web3BnbTransaction::where('is_processed', false)->get();

        foreach ($unprocessedTransactions as $transaction) {
            $this->processUserBnbAsset($transaction);
            $transaction->is_processed = true;
            $transaction->save();
        }

        return count($unprocessedTransactions);
    }

    private function processNewMetaTransactions()
    {
        $unprocessedTransactions = Web3MetaTransaction::where('is_processed', false)->get();

        foreach ($unprocessedTransactions as $transaction) {
            $this->processUserMetaAsset($transaction);
            $transaction->is_processed = true;
            $transaction->save();
        }

        return count($unprocessedTransactions);
    }

    private function processUserBnbAsset($transaction)
    {
        $bankAddress = strtolower($this->bankAddress);
        $fromAddress = strtolower($transaction->from_address);
        $toAddress = strtolower($transaction->to_address);

        if ($toAddress === $bankAddress) {
            $user = User::whereRaw('LOWER(address) = ?', [$fromAddress])->first();
            if (!$user) {
                return;
            }
            $result = $user->addAsset('bnb', $transaction->amount);
            $logMessage = "BNB Deposit: Updated BNB balance for user {$user->id}: +{$transaction->amount}";
        } elseif ($fromAddress === $bankAddress) {
            $user = User::whereRaw('LOWER(address) = ?', [$toAddress])->first();
            if (!$user) {
                return;
            }
            $result = $user->removeAsset('bnb', $transaction->amount);
            $logMessage = "BNB Withdrawal: Updated BNB balance for user {$user->id}: -{$transaction->amount}";
        } else {
            return;
        }

        if (!$result) {
            Log::error("Failed to update BNB balance", ['message' => $logMessage]);
        }
    }

    private function processUserMetaAsset($transaction)
    {
        $bankAddress = strtolower($this->bankAddress);
        $fromAddress = strtolower($transaction->from_address);
        $toAddress = strtolower($transaction->to_address);

        if ($toAddress === $bankAddress) {
            $user = User::whereRaw('LOWER(address) = ?', [$fromAddress])->first();
            if (!$user) {
                return;
            }
            $result = $user->addAsset('meta', $transaction->amount);
            $logMessage = "META Deposit: Updated META balance for user {$user->id}: +{$transaction->amount}";
        } elseif ($fromAddress === $bankAddress) {
            $user = User::whereRaw('LOWER(address) = ?', [$toAddress])->first();
            if (!$user) {
                return;
            }
            $result = $user->removeAsset('meta', $transaction->amount);
            $logMessage = "META Withdrawal: Updated META balance for user {$user->id}: -{$transaction->amount}";
        } else {
            return;
        }

        if (!$result) {
            Log::error("Failed to update META balance", ['message' => $logMessage]);
        }
    }

    public function updateBalances()
    {
        $web3Controller = new Web3Controller();
        $web3Controller->checkNewTransactions();
        return $this->processNewTransactions();
    }
}