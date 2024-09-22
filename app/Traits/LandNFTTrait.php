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
        $tokenId = $land->id;
        $approvedAddress = $this->nftController->getApproved($tokenId);
        $bankAddress = env('BANK_ADDRESS');
        $owner = $land->owner;
        $isOwnerBank = $owner->role === 'bank';
        $isApprovedByBank = $approvedAddress !== null && strtolower($approvedAddress) === strtolower($bankAddress);
        $result = $isOwnerBank || $isApprovedByBank;
        return $result;
    }

    public function handleLandNFT(Land $land, User $buyer)
    {
        $tokenId = $land->id;
        $currentOwner = $this->nftController->getTokenOwner($tokenId);
        $approvedAddress = $this->nftController->getApproved($tokenId);
        $bankAddress = env('BANK_ADDRESS');
        if ($currentOwner === null || $approvedAddress === null) {
            // This is where we'll create and upload the metadata
            $metadataUri = $this->createAndUploadMetadata($land);
            $txHash = $this->nftController->mintNFT($buyer->address, $tokenId, $metadataUri);
            $this->updateLandOwnership($land, $buyer->id, $metadataUri);
            return ['action' => 'minted', 'txHash' => $txHash];
        } elseif (strtolower($currentOwner) === strtolower($buyer->address)) {
            $this->updateLandOwnership($land, $buyer->id);
            return ['action' => 'database_updated', 'txHash' => null];
        } elseif (strtolower($approvedAddress) === strtolower($bankAddress)) {
            $txHash = $this->nftController->transferFrom($currentOwner, $buyer->address, $tokenId);
            $this->updateLandOwnership($land, $buyer->id);
            return ['action' => 'transferred', 'txHash' => $txHash];
        } else {
            $blockchainOwner = User::whereRaw('LOWER(address) = ?', [strtolower($currentOwner)])->first();
            if ($blockchainOwner) {
                $this->updateLandOwnership($land, $blockchainOwner->id);
                return ['action' => 'database_updated', 'txHash' => null];
            } else {
                throw new \Exception("Blockchain owner not found in database for land {$land->id}");
            }
        }
    }

    private function createAndUploadMetadata(Land $land)
    {
        // First, upload the image
        $imagePath = public_path('img/building-empty.png');
        $imageIpfsHash = $this->uploadToPinata($imagePath);

        // Create metadata
        $metadata = [
            "name" => "Land-#{$land->id}",
            "description" => "A plot of land in the Metareal game",
            "image" => "ipfs://{$imageIpfsHash}",
            "attributes" => [
                [
                    "trait_type" => "Size",
                    "value" => $land->size
                ],
                // Add more attributes as needed
            ]
        ];

        // Convert metadata to JSON
        $jsonMetadata = json_encode($metadata);

        // Upload JSON metadata to IPFS
        $metadataIpfsHash = $this->uploadJsonToPinata($jsonMetadata);

        return "ipfs://{$metadataIpfsHash}";
    }

    private function uploadToPinata($filePath)
    {
        $url = "https://api.pinata.cloud/pinning/pinFileToIPFS";
        $apiKey = env('PINATA_API_KEY');
        $apiSecret = env('PINATA_API_SECRET');

        $response = Http::withHeaders([
            'pinata_api_key' => $apiKey,
            'pinata_secret_api_key' => $apiSecret,
        ])->attach(
            'file', file_get_contents($filePath), basename($filePath)
        )->post($url);

        if ($response->successful()) {
            return $response->json()['IpfsHash'];
        } else {
            throw new \Exception("Failed to upload file to Pinata: " . $response->body());
        }
    }

    private function uploadJsonToPinata($jsonContent)
    {
        $url = "https://api.pinata.cloud/pinning/pinJSONToIPFS";
        $apiKey = env('PINATA_API_KEY');
        $apiSecret = env('PINATA_API_SECRET');

        $response = Http::withHeaders([
            'pinata_api_key' => $apiKey,
            'pinata_secret_api_key' => $apiSecret,
            'Content-Type' => 'application/json',
        ])->post($url, json_decode($jsonContent, true));

        if ($response->successful()) {
            return $response->json()['IpfsHash'];
        } else {
            throw new \Exception("Failed to upload JSON to Pinata: " . $response->body());
        }
    }

    private function updateLandOwnership(Land $land, int $newOwnerId, ?string $ipfsUrl = null)
    {
        $updateData = [
            'owner_id' => $newOwnerId,
            'fixed_price' => 0,
        ];

        if ($ipfsUrl !== null) {
            $updateData['ipfs_url'] = $ipfsUrl;
        }

        $land->update($updateData);
    }
}