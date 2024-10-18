<?php

namespace App\Traits;

use App\Models\Land;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Log;

trait LandNFTTrait
{
    public function isLandApprovedForTransfer(Land $land): bool
    {
        try {
            $tokenId = $land->id;
            $approvedAddress = $this->nftController->getApproved($tokenId);
            $bankAddress = env('BANK_ADDRESS');
            $owner = $land->owner;
            $isOwnerBank = $owner->role === 'bank';
            $isApprovedByBank = $approvedAddress !== null && strtolower($approvedAddress) === strtolower($bankAddress);
            $result = $isOwnerBank || $isApprovedByBank;
            Log::info("Land {$land->id} approval check result: " . ($result ? 'Approved' : 'Not Approved'));
            return $result;
        } catch (\Exception $e) {
            Log::error("Error in isLandApprovedForTransfer for land {$land->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function mintNftForBuyer(Land $land, User $buyer)
    {
        try {
            $tokenId = $land->id;
            $metadataUri = $this->createAndUploadMetadata($land);
            $txHash = $this->nftController->mintNFT($buyer->address, $tokenId, $metadataUri);
            $this->updateLandOwnership($land, $buyer->id, $metadataUri);
            Log::info("NFT minted for land {$land->id} to buyer {$buyer->id}. Transaction hash: {$txHash}");
            return ['action' => 'minted', 'txHash' => $txHash];
        } catch (\Exception $e) {
            Log::error("Error in mintNftForBuyer for land {$land->id} and buyer {$buyer->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function handleLandNFT(Land $land, User $buyer)
    {
        try {
            $tokenId = $land->id;
            $currentOwner = $this->nftController->getTokenOwner($tokenId);
            $approvedAddress = $this->nftController->getApproved($tokenId);
            $bankAddress = env('BANK_ADDRESS');

            Log::info("Handling NFT for land {$land->id}. Current owner: {$currentOwner}, Approved address: {$approvedAddress}");

            if ($currentOwner === null || $approvedAddress === null) {
                Log::info("Minting new NFT for land {$land->id}");
                return $this->mintNftForBuyer($land, $buyer);
            } elseif (strtolower($currentOwner) === strtolower($buyer->address)) {
                Log::info("Updating ownership in database for land {$land->id}");
                $this->updateLandOwnership($land, $buyer->id);
                return ['action' => 'database_updated', 'txHash' => null];
            } elseif (strtolower($approvedAddress) === strtolower($bankAddress)) {
                Log::info("Transferring NFT for land {$land->id} from {$currentOwner} to {$buyer->address}");
                $txHash = $this->nftController->transferFrom($currentOwner, $buyer->address, $tokenId);
                $this->updateLandOwnership($land, $buyer->id);
                return ['action' => 'transferred', 'txHash' => $txHash];
            } else {
                $blockchainOwner = User::whereRaw('LOWER(address) = ?', [strtolower($currentOwner)])->first();
                if ($blockchainOwner) {
                    Log::info("Updating ownership in database for land {$land->id} to match blockchain");
                    $this->updateLandOwnership($land, $blockchainOwner->id);
                    return ['action' => 'database_updated', 'txHash' => null];
                } else {
                    Log::error("Blockchain owner not found in database for land {$land->id}");
                    return response()->json(['error' => 'Blockchain owner not found in database.'], 500);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error in handleLandNFT for land {$land->id} and buyer {$buyer->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function createAndUploadMetadata(Land $land)
    {
        try {
            $imagePath = public_path('img/building-empty.png');
            $imageIpfsHash = $this->uploadToPinata($imagePath);
            Log::info("Image uploaded to IPFS for land {$land->id}. Hash: {$imageIpfsHash}");

            $metadata = [
                "name" => "Land-#{$land->id}",
                "description" => "A plot of land in the Metareal game",
                "image" => "ipfs://{$imageIpfsHash}",
                "attributes" => [
                    [
                        "trait_type" => "Size",
                        "value" => $land->size
                    ],
                ]
            ];

            $jsonMetadata = json_encode($metadata);
            $metadataIpfsHash = $this->uploadJsonToPinata($jsonMetadata);
            Log::info("Metadata uploaded to IPFS for land {$land->id}. Hash: {$metadataIpfsHash}");

            return "ipfs://{$metadataIpfsHash}";
        } catch (\Exception $e) {
            Log::error("Error in createAndUploadMetadata for land {$land->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function uploadToPinata($filePath)
    {
        try {
            $url = "https://api.pinata.cloud/pinning/pinFileToIPFS";
            $apiKey = env('PINATA_API_KEY');
            $apiSecret = env('PINATA_API_SECRET');

            $response = Http::withHeaders([
                'pinata_api_key' => $apiKey,
                'pinata_secret_api_key' => $apiSecret,
            ])->attach(
                'file',
                file_get_contents($filePath),
                basename($filePath)
            )->post($url);

            if ($response->successful()) {
                $ipfsHash = $response->json()['IpfsHash'];
                Log::info("File uploaded to Pinata. IPFS Hash: {$ipfsHash}");
                return $ipfsHash;
            } else {
                Log::error("Failed to upload file to Pinata: " . $response->body());
                return response()->json(['error' => 'Failed to upload file to Pinata: ' . $response->body()], 500);
            }
        } catch (\Exception $e) {
            Log::error("Error in uploadToPinata: " . $e->getMessage());
            throw $e;
        }
    }

    private function uploadJsonToPinata($jsonContent)
    {
        try {
            $url = "https://api.pinata.cloud/pinning/pinJSONToIPFS";
            $apiKey = env('PINATA_API_KEY');
            $apiSecret = env('PINATA_API_SECRET');

            $response = Http::withHeaders([
                'pinata_api_key' => $apiKey,
                'pinata_secret_api_key' => $apiSecret,
                'Content-Type' => 'application/json',
            ])->post($url, json_decode($jsonContent, true));

            if ($response->successful()) {
                $ipfsHash = $response->json()['IpfsHash'];
                Log::info("JSON uploaded to Pinata. IPFS Hash: {$ipfsHash}");
                return $ipfsHash;
            } else {
                Log::error("Failed to upload JSON to Pinata: " . $response->body());
                return response()->json(['error' => 'Failed to upload JSON to Pinata: ' . $response->body()], 500);
            }
        } catch (\Exception $e) {
            Log::error("Error in uploadJsonToPinata: " . $e->getMessage());
            throw $e;
        }
    }

    private function updateLandOwnership(Land $land, int $newOwnerId, ?string $ipfsUrl = null)
    {
        try {
            $updateData = [
                'owner_id' => $newOwnerId,
                'fixed_price' => 0,
            ];

            if ($ipfsUrl !== null) {
                $updateData['ipfs_url'] = $ipfsUrl;
            }

            $land->update($updateData);
            Log::info("Land ownership updated for land {$land->id}. New owner ID: {$newOwnerId}");
        } catch (\Exception $e) {
            Log::error("Error in updateLandOwnership for land {$land->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
