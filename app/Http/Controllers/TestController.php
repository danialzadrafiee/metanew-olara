<?php

namespace App\Http\Controllers;

use Web3\Providers\HttpProvider;
use Web3\Web3;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    private $web3;
    private $contractAddress = '0x3647475ba87ac9A788f8533751c02A09A51DA556';
    private $ownerOfSignature = '0x6352211e';

    public function __construct()
    {
        $this->web3 = new Web3(new HttpProvider('http://127.0.0.1:8545'));
    }

    public function getNftContractTransactions()
    {
        $tokensAndOwners = [];
        $tokenId = 1;
        $continue = true;
        while ($continue) {
            $data = $this->ownerOfSignature . str_pad(dechex($tokenId), 64, '0', STR_PAD_LEFT);

            $this->web3->eth->call([
                'to' => $this->contractAddress,
                'data' => $data
            ], 'latest', function ($err, $result) use (&$tokensAndOwners, $tokenId, &$continue) {
                if ($err !== null) {
                    Log::error("Reached end or error at token ID $tokenId: " . $err->getMessage());
                    $continue = false;
                } else if ($result === '0x') {
                    Log::info("No owner found for token ID $tokenId. Stopping.");
                    $continue = false;
                } else {
                    $owner = '0x' . substr($result, 26);
                    $tokensAndOwners[] = [
                        'tokenId' => $tokenId,
                        'owner' => $owner
                    ];
                }
            });

            if ($continue) {
                $tokenId++;
            }
        }

        return response()->json($tokensAndOwners);
    }
}