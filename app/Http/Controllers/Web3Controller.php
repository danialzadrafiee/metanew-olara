<?php

namespace App\Http\Controllers;

use Web3\Web3;
use Web3\Contract;
use App\Models\Web3BnbTransaction;
use App\Models\Web3MetaTransaction;
use Illuminate\Support\Facades\Log;

class Web3Controller extends Controller
{
    private $bnbDepositAddress = '0x24015B83f9B2CD8BF831101e79b3BFB9aE20afa1';
    private $metaContractAddress = '0x523c2d041153D299602468684bfeC9a7e448eDB0';
    private $web3;
    private $metaContract;

    public function __construct()
    {
        $this->web3 = new Web3('http://127.0.0.1:8545');
        $this->metaContract = new Contract($this->web3->provider, $this->getMetaAbi());
        $this->metaContract->at($this->metaContractAddress);
    }

    public function checkNewTransactions()
    {
        try {
            $latestBlock = $this->getLatestBlockNumber();
            if ($latestBlock === null) {
                Log::error('Failed to get latest block number');
                return;
            }

            $newBnbTransactions = $this->fetchNewBnbTransactions($latestBlock);
            $this->saveNewBnbTransactions($newBnbTransactions);

            $newMetaTransactions = $this->fetchNewMetaTransactions($latestBlock);
            $this->saveNewMetaTransactions($newMetaTransactions);

        } catch (\Exception $e) {
            Log::error('Error in checkNewTransactions: ' . $e->getMessage());
        }
    }

    private function getLatestBlockNumber()
    {
        $latestBlock = null;
        $this->web3->eth->blockNumber(function ($err, $blockNumber) use (&$latestBlock) {
            if ($err !== null) {
                Log::error('Error getting latest block number: ' . $err->getMessage());
            } else {
                $latestBlock = hexdec($blockNumber);
            }
        });
        return $latestBlock;
    }

    private function fetchNewBnbTransactions($latestBlock)
    {
        $transactions = [];
        $blockNumber = $latestBlock;
        $foundExistingTransaction = false;

        while ($blockNumber > 0 && !$foundExistingTransaction) {
            $blockTransactions = $this->getBnbTransactions($blockNumber);

            foreach ($blockTransactions as $tx) {
                if ($this->bnbTransactionExists($tx['tx_hash'])) {
                    $foundExistingTransaction = true;
                    break;
                }
                $transactions[] = $tx;
            }

            $blockNumber--;
        }

        return array_reverse($transactions);
    }

    private function fetchNewMetaTransactions($latestBlock)
    {
        $transactions = [];
        $blockNumber = $latestBlock;
        $foundExistingTransaction = false;

        while ($blockNumber > 0 && !$foundExistingTransaction) {
            $blockTransactions = $this->getMetaTransactions($blockNumber);

            foreach ($blockTransactions as $tx) {
                if ($this->metaTransactionExists($tx['tx_hash'])) {
                    $foundExistingTransaction = true;
                    break;
                }
                $transactions[] = $tx;
            }

            $blockNumber--;
        }

        return array_reverse($transactions);
    }

    private function getBnbTransactions($blockNumber)
    {
        $transactions = [];
        $this->web3->eth->getBlockByNumber($blockNumber, true, function ($err, $block) use (&$transactions, $blockNumber) {
            if ($err !== null) {
                Log::error("Error getting block $blockNumber: " . $err->getMessage());
            } elseif ($block === null) {
                Log::warning("Block $blockNumber returned null");
            } else {
                if (!property_exists($block, 'transactions')) {
                    Log::warning("Block $blockNumber does not have a transactions property");
                } else {
                    foreach ($block->transactions as $tx) {
                        $toAddress = strtolower($tx->to);
                        $fromAddress = strtolower($tx->from);
                        $depositAddress = strtolower($this->bnbDepositAddress);

                        if ($toAddress === $depositAddress || $fromAddress === $depositAddress) {
                            $transactions[] = $this->formatBnbTransaction($tx, $block);
                        }
                    }
                }
            }
        });
        return $transactions;
    }

    private function getMetaTransactions($blockNumber)
    {
        $transactions = [];
        $this->web3->eth->getBlockByNumber($blockNumber, true, function ($err, $block) use (&$transactions, $blockNumber) {
            if ($err !== null) {
                Log::error("Error getting block $blockNumber: " . $err->getMessage());
            } elseif ($block === null) {
                Log::warning("Block $blockNumber returned null");
            } else {
                if (!property_exists($block, 'transactions')) {
                    Log::warning("Block $blockNumber does not have a transactions property");
                } else {
                    foreach ($block->transactions as $tx) {
                        if (strtolower($tx->to) === strtolower($this->metaContractAddress)) {
                            $decodedInput = $this->decodeMetaTransferInput($tx->input);
                            if ($decodedInput) {
                                $transactions[] = $this->formatMetaTransaction($tx, $block, $decodedInput);
                            }
                        }
                    }
                }
            }
        });
        return $transactions;
    }

    private function formatBnbTransaction($tx, $block)
    {
        return [
            'tx_hash' => $tx->hash,
            'from_address' => $tx->from,
            'to_address' => $tx->to,
            'amount' => $this->weiToEth($tx->value),
            'block_number' => hexdec($block->number),
        ];
    }

    private function formatMetaTransaction($tx, $block, $decodedInput)
    {
        return [
            'tx_hash' => $tx->hash,
            'from_address' => $tx->from,
            'to_address' => $decodedInput['to'],
            'amount' => $this->weiToMeta($decodedInput['value']),
            'block_number' => hexdec($block->number),
        ];
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

    private function weiToEth($wei)
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

    private function weiToMeta($wei)
    {

        if (is_string($wei) && substr($wei, 0, 2) === '0x') {
            $wei = gmp_strval(gmp_init(substr($wei, 2), 16));
        }

        $wei = (string)$wei;

        if (!is_numeric($wei)) {
            Log::error("Invalid wei value: " . $wei);
            return '0';
        }

        $result = bcdiv($wei, bcpow('10', '8'), 8);


        return $result;
    }

    private function decodeMetaTransferInput($input)
    {
        if (substr($input, 0, 10) !== '0xa9059cbb') {
            return null;
        }

        $to = '0x' . substr($input, 34, 40);
        $value = hexdec(substr($input, 74));

        return [
            'to' => $to,
            'value' => $value,
        ];
    }

    private function getMetaAbi()
    {
        return json_decode('[{"constant":true,"inputs":[],"name":"name","outputs":[{"name":"","type":"string"}],"type":"function"},{"constant":false,"inputs":[{"name":"_spender","type":"address"},{"name":"_value","type":"uint256"}],"name":"approve","outputs":[{"name":"success","type":"bool"}],"type":"function"},{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"name":"","type":"uint256"}],"type":"function"},{"constant":false,"inputs":[{"name":"_from","type":"address"},{"name":"_to","type":"address"},{"name":"_value","type":"uint256"}],"name":"transferFrom","outputs":[{"name":"success","type":"bool"}],"type":"function"},{"constant":true,"inputs":[],"name":"decimals","outputs":[{"name":"","type":"uint8"}],"type":"function"},{"constant":true,"inputs":[{"name":"_owner","type":"address"}],"name":"balanceOf","outputs":[{"name":"balance","type":"uint256"}],"type":"function"},{"constant":true,"inputs":[],"name":"symbol","outputs":[{"name":"","type":"string"}],"type":"function"},{"constant":false,"inputs":[{"name":"_to","type":"address"},{"name":"_value","type":"uint256"}],"name":"transfer","outputs":[{"name":"success","type":"bool"}],"type":"function"},{"constant":true,"inputs":[{"name":"_owner","type":"address"},{"name":"_spender","type":"address"}],"name":"allowance","outputs":[{"name":"remaining","type":"uint256"}],"type":"function"},{"anonymous":false,"inputs":[{"indexed":true,"name":"_from","type":"address"},{"indexed":true,"name":"_to","type":"address"},{"indexed":false,"name":"_value","type":"uint256"}],"name":"Transfer","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"_owner","type":"address"},{"indexed":true,"name":"_spender","type":"address"},{"indexed":false,"name":"_value","type":"uint256"}],"name":"Approval","type":"event"}]', true);
    }
}
