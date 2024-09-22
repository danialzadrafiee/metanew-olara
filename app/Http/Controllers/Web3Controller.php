<?php

namespace App\Http\Controllers;

use App\Models\Web3BnbTransaction;
use App\Models\Web3MetaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Web3Controller extends Controller
{
    private $bankAddress;
    private $metaContractAddress;
    private $etherscanApiKey;
    private $etherscanApiUrl;

    public function __construct()
    {
        $this->bankAddress = env('BANK_ADDRESS');
        $this->metaContractAddress = env('META_CONTRACT_ADDRESS');
        $this->etherscanApiKey = env('ETHERSCAN_API_KEY');
        $this->etherscanApiUrl = env('ETHERSCAN_API_URL');
    }

    public function checkNewTransactions()
    {
        try {
            $newBnbTransactions = $this->fetchNewBnbTransactions();
            $this->saveNewBnbTransactions($newBnbTransactions);

            $newMetaTransactions = $this->fetchNewMetaTransactions();
            $this->saveNewMetaTransactions($newMetaTransactions);
        } catch (\Exception $e) {
            Log::error('Error in checkNewTransactions: ' . $e->getMessage());
        }
    }

    private function fetchNewBnbTransactions()
    {
        $response = Http::get($this->etherscanApiUrl, [
            'module' => 'account',
            'action' => 'txlist',
            'address' => $this->bankAddress,
            'startblock' => 0,
            'endblock' => 99999999,
            'sort' => 'desc',
            'apikey' => $this->etherscanApiKey,
        ]);

        if ($response->successful()) {
            $transactions = $response->json()['result'];
            return $this->formatBnbTransactions($transactions);
        } else {
            Log::error('Failed to fetch BNB transactions: ' . $response->body());
            return [];
        }
    }

    private function fetchNewMetaTransactions()
    {
        $response = Http::get($this->etherscanApiUrl, [
            'module' => 'account',
            'action' => 'tokentx',
            'contractaddress' => $this->metaContractAddress,
            'address' => $this->bankAddress,
            'startblock' => 0,
            'endblock' => 99999999,
            'sort' => 'desc',
            'apikey' => $this->etherscanApiKey,
        ]);

        if ($response->successful()) {
            $transactions = $response->json()['result'];
            return $this->formatMetaTransactions($transactions);
        } else {
            Log::error('Failed to fetch META transactions: ' . $response->body());
            return [];
        }
    }

    private function formatBnbTransactions($transactions)
    {
        $formattedTransactions = [];
        foreach ($transactions as $tx) {
            if (!$this->bnbTransactionExists($tx['hash'])) {
                $formattedTransactions[] = [
                    'tx_hash' => $tx['hash'],
                    'from_address' => $tx['from'],
                    'to_address' => $tx['to'],
                    'amount' => $this->weiToBNB($tx['value']),
                    'block_number' => $tx['blockNumber'],
                ];
            }
        }
        return $formattedTransactions;
    }

    private function formatMetaTransactions($transactions)
    {
        $formattedTransactions = [];
        foreach ($transactions as $tx) {
            if (!$this->metaTransactionExists($tx['hash'])) {
                $formattedTransactions[] = [
                    'tx_hash' => $tx['hash'],
                    'from_address' => $tx['from'],
                    'to_address' => $tx['to'],
                    'amount' => $this->weiToMETA($tx['value']),
                    'block_number' => $tx['blockNumber'],
                ];
            }
        }
        return $formattedTransactions;
    }

    private function bnbTransactionExists($txHash)
    {
        return Web3BnbTransaction::where('tx_hash', $txHash)->exists();
    }

    private function metaTransactionExists($txHash)
    {
        return Web3MetaTransaction::where('tx_hash', $txHash)->exists();
    }

    private function saveNewBnbTransactions($transactions)
    {
        foreach ($transactions as $tx) {
            try {
                Web3BnbTransaction::firstOrCreate(
                    ['tx_hash' => $tx['tx_hash']],
                    [
                        'from_address' => $tx['from_address'],
                        'to_address' => $tx['to_address'],
                        'amount' => $tx['amount'],
                        'block_number' => $tx['block_number'],
                        'is_processed' => false
                    ]
                );
            } catch (\Exception $e) {
                Log::error("Failed to save BNB transaction {$tx['tx_hash']}: " . $e->getMessage());
            }
        }
    }

    private function saveNewMetaTransactions($transactions)
    {
        foreach ($transactions as $tx) {
            try {
                Web3MetaTransaction::firstOrCreate(
                    ['tx_hash' => $tx['tx_hash']],
                    [
                        'from_address' => $tx['from_address'],
                        'to_address' => $tx['to_address'],
                        'amount' => $tx['amount'],
                        'block_number' => $tx['block_number'],
                        'is_processed' => false
                    ]
                );
            } catch (\Exception $e) {
                Log::error("Failed to save META transaction {$tx['tx_hash']}: " . $e->getMessage());
            }
        }
    }

    private function weiToBNB($wei)
    {
        if (is_string($wei) && substr($wei, 0, 2) === '0x') {
            $wei = gmp_strval(gmp_init(substr($wei, 2), 16));
        }

        $wei = (string)$wei;

        if (!is_numeric($wei)) {
            Log::error("Invalid wei value: " . $wei);
            return '0';
        }

        $result = bcdiv($wei, bcpow('10', '18'), 18);

        return $result;
    }

    private function weiToMETA($wei)
    {
        if (is_string($wei) && substr($wei, 0, 2) === '0x') {
            $wei = gmp_strval(gmp_init(substr($wei, 2), 16));
        }

        $wei = (string)$wei;

        if (!is_numeric($wei)) {
            Log::error("Invalid wei value: " . $wei);
            return '0';
        }

        $result = bcdiv($wei, bcpow('10', '18'), 18);  // Assuming META has 8 decimal places

        return $result;
    }
}
