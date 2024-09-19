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
    private $fromAddress = '0x24015B83f9B2CD8BF831101e79b3BFB9aE20afa1';
    private $privateKey = '0x6ede5877c85dfb5c94d78ab2271bfa8fe3782d6c548470f948b7b17b698809cd';
    private $contract;
    private $contractAddress = '0x3647475ba87ac9A788f8533751c02A09A51DA556';

    public function __construct()
    {
        $this->web3 = new Web3(new HttpProvider('http://127.0.0.1:8545'));
        $this->contract = new Contract($this->web3->provider, NFTAbi::getNFTAbi());
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
            'from' => $this->fromAddress,
            'to' => $this->contractAddress,
            'gas' => '0x' . dechex(200000),
            'value' => '0x0',
            'data' => '0x' . $data,
            'chainId' => 1337
        ];

        $eth->getTransactionCount($this->fromAddress, 'pending', function ($err, $nonce) use (&$transactionParams) {
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
            'from' => $this->fromAddress,
            'to' => $this->contractAddress,
            'gas' => '0x' . dechex(200000),
            'value' => '0x0',
            'data' => '0x' . $data,
            'chainId' => 1337
        ];

        $eth->getTransactionCount($this->fromAddress, 'pending', function ($err, $nonce) use (&$transactionParams) {
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
        $signedTransaction = '0x' . $transaction->sign(trim($this->privateKey, '0x'));

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