<?php

namespace App\Http\Controllers;

use App\Helpers\NFTAbi;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\Web3;
use Web3p\EthereumTx\Transaction;

class NftController extends Controller
{
    private $web3;
    private $bankPrivateKey;
    private $bankAddress;
    private $contract;
    private $contractAddress;

    public function __construct()
    {
        $this->web3 = new Web3(new HttpProvider(env('RPC_URL')));
        $this->contract = new Contract($this->web3->provider, NFTAbi::getNFTAbi());
        $this->bankPrivateKey = env('BANK_PVK');
        $this->bankAddress = env('BANK_ADDRESS');
        $this->contractAddress = env('LAND_MINTER_CONTRACT_ADDRESS');
    }

    public function getTokenOwner($tokenId)
    {
        $owner = null;
        try {
            $this->contract->at($this->contractAddress)->call('ownerOf', $tokenId, function ($err, $result) use (&$owner) {
                if ($err === null) {
                    $owner = is_array($result) ? $result[0] : $result;
                }
            });
            return $owner;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function getApproved($tokenId) : ?string
    {
        $approvedAddress = null;
        try {
            $this->contract->at($this->contractAddress)->call('getApproved', $tokenId, function ($err, $result) use (&$approvedAddress) {
                if ($err === null) {
                    $approvedAddress = is_array($result) ? $result[0] : $result;
                }
            });
            return $approvedAddress;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function tokenExists($tokenId)
    {
        $exists = false;
        $this->contract->at($this->contractAddress)->call('ownerOf', $tokenId, function ($err, $result) use (&$exists) {
            if ($err === null && $result) {
                $exists = true;
            }
        });
        return $exists;
    }

    public function mintNFT($to, $tokenId, $uri)
    {
        $this->contract->at($this->contractAddress)->call('tokenURI', $tokenId, function ($err, $result) use ($tokenId) {
            if ($result) {
                throw new \Exception("Token ID {$tokenId} already exists.");
            }
        });

        $params = [
            'to' => $to,
            'tokenId' => $tokenId,
            'uri' => $uri,
        ];
        $eth = $this->web3->eth;

        $data = $this->contract->at($this->contractAddress)->getData('mint', $params['to'], $params['tokenId'], $params['uri']);

        $transactionParams = [
            'from' => $this->bankAddress,
            'to' => $this->contractAddress,
            'gas' => '0x' . dechex(200000),
            'value' => '0x0',
            'data' => '0x' . $data,
            'chainId' => env('CHAIN_ID'),
        ];

        $eth->getTransactionCount($this->bankAddress, 'pending', function ($err, $nonce) use (&$transactionParams) {
            if ($err !== null) {
                throw new \Exception('Error getting nonce: ' . $err->getMessage());
            }
            $transactionParams['nonce'] = '0x' . $nonce->toHex(true);
        });

        $eth->gasPrice(function ($err, $gasPrice) use (&$transactionParams) {
            if ($err !== null) {
                throw new \Exception('Error getting gas price: ' . $err->getMessage());
            }
            $transactionParams['gasPrice'] = '0x' . $gasPrice->toHex(true);
        });

        $transaction = new Transaction($transactionParams);
        $signedTransaction = '0x' . $transaction->sign(trim($this->bankPrivateKey, '0x'));

        $txHash = null;
        $eth->sendRawTransaction($signedTransaction, function ($err, $hash) use (&$txHash) {
            if ($err !== null) {
                throw new \Exception('Error sending transaction: ' . $err->getMessage());
            }
            $txHash = $hash;
        });

        return $txHash;
    }

    public function transferFrom($from, $to, $tokenId)
    {
        $params = [
            'from' => $from,
            'to' => $to,
            'tokenId' => $tokenId,
        ];
        $eth = $this->web3->eth;

        $data = $this->contract->at($this->contractAddress)->getData('transferFrom', $params['from'], $params['to'], $params['tokenId']);

        $transactionParams = [
            'from' => $this->bankAddress,
            'to' => $this->contractAddress,
            'gas' => '0x' . dechex(200000),
            'value' => '0x0',
            'data' => '0x' . $data,
            'chainId' => env('CHAIN_ID'),
        ];

        $eth->getTransactionCount($this->bankAddress, 'pending', function ($err, $nonce) use (&$transactionParams) {
            if ($err !== null) {
                throw new \Exception('Error getting nonce: ' . json_encode($err));
            }
            $transactionParams['nonce'] = '0x' . $nonce->toHex(true);
        });

        $eth->gasPrice(function ($err, $gasPrice) use (&$transactionParams) {
            if ($err !== null) {
                throw new \Exception('Error getting gas price: ' . json_encode($err));
            }
            $transactionParams['gasPrice'] = '0x' . $gasPrice->toHex(true);
        });

        $transaction = new Transaction($transactionParams);
        $signedTransaction = '0x' . $transaction->sign(trim($this->bankPrivateKey, '0x'));

        $txHash = null;
        $eth->sendRawTransaction($signedTransaction, function ($err, $hash) use (&$txHash) {
            if ($err !== null) {
                throw new \Exception('Error sending transaction: ' . json_encode($err));
            }
            $txHash = $hash;
        });

        if ($txHash === null) {
            throw new \Exception('Transaction hash is null after sending transaction');
        }

        return $txHash;
    }
}