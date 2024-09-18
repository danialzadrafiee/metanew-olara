<?php

namespace App\Http\Controllers;

use App\Models\Web3BnbTransaction;
use App\Models\Web3MetaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SpotController extends Controller
{
    private $depositAddress = '0x24015B83f9B2CD8BF831101e79b3BFB9aE20afa1';

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
        $depositAddress = strtolower($this->depositAddress);
        $fromAddress = strtolower($transaction->from_address);
        $toAddress = strtolower($transaction->to_address);

        if ($toAddress === $depositAddress) {
            $user = User::whereRaw('LOWER(address) = ?', [$fromAddress])->first();
            if (!$user) {
                Log::warning("BNB deposit received from unknown address: {$fromAddress}");
                return;
            }
            $result = $user->addAsset('bnb', $transaction->amount);
            $logMessage = "BNB Deposit: Updated BNB balance for user {$user->id}: +{$transaction->amount}";
        } elseif ($fromAddress === $depositAddress) {
            $user = User::whereRaw('LOWER(address) = ?', [$toAddress])->first();
            if (!$user) {
                Log::warning("BNB withdrawal sent to unknown address: {$toAddress}");
                return;
            }
            $result = $user->removeAsset('bnb', $transaction->amount);
            $logMessage = "BNB Withdrawal: Updated BNB balance for user {$user->id}: -{$transaction->amount}";
        } else {
            Log::warning("BNB transaction does not involve deposit address: from {$fromAddress} to {$toAddress}");
            return;
        }

        if ($result) {
            Log::info($logMessage);
        } else {
            Log::error("Failed to update BNB balance: {$logMessage}");
        }
    }

    private function processUserMetaAsset($transaction)
    {
        $depositAddress = strtolower($this->depositAddress);
        $fromAddress = strtolower($transaction->from_address);
        $toAddress = strtolower($transaction->to_address);

        // Log the raw amount
        Log::info("Raw META transaction amount: " . $transaction->amount);

        $amount = $transaction->amount;

        if ($toAddress === $depositAddress) {
            $user = User::whereRaw('LOWER(address) = ?', [$fromAddress])->first();
            if (!$user) {
                Log::warning("META deposit received from unknown address: {$fromAddress}");
                return;
            }
            $result = $user->addAsset('meta', $amount);
            $logMessage = "META Deposit: Updated META balance for user {$user->id}: +{$amount}";
        } elseif ($fromAddress === $depositAddress) {
            $user = User::whereRaw('LOWER(address) = ?', [$toAddress])->first();
            if (!$user) {
                Log::warning("META withdrawal sent to unknown address: {$toAddress}");
                return;
            }
            $result = $user->removeAsset('meta', $amount);
            $logMessage = "META Withdrawal: Updated META balance for user {$user->id}: -{$amount}";
        } else {
            Log::warning("META transaction does not involve deposit address: from {$fromAddress} to {$toAddress}");
            return;
        }

        if ($result) {
            Log::info($logMessage);
        } else {
            Log::error("Failed to update META balance: {$logMessage}");
        }
    }

    public function updateBalances()
    {
        $web3Controller = new Web3Controller();
        $web3Controller->checkNewTransactions();
        return $this->processNewTransactions();
    }
}