<?php
namespace App\Http\Controllers;
use App\Models\Land;
use App\Models\LandTransfer;
use App\Models\User;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class LandTransferController extends Controller
{
    protected $nftController;
    public function __construct(NftController $nftController)
    {
        $this->nftController = $nftController;
    }
    private function isLandApprovedForTransfer(Land $land): bool
    {
        $tokenId = $land->id;
        $approvedAddress = $this->nftController->getApproved($tokenId);
        $bankAddress = "0x24015B83f9B2CD8BF831101e79b3BFB9aE20afa1";
        return strtolower($approvedAddress) === strtolower($bankAddress);
    }
    private function handleLandNFT(Land $land, User $buyer)
    {
        $tokenId = $land->id;
        $currentOwner = $this->nftController->getTokenOwner($tokenId);
        $approvedAddress = $this->nftController->getApproved($tokenId);
        $bankAddress = "0x24015B83f9B2CD8BF831101e79b3BFB9aE20afa1";
        if ($currentOwner === null || $approvedAddress === null) {
            $txHash = $this->nftController->mintNFT($buyer->address, $tokenId, "https://ipfs.io/ipfs/bafybeibulyuw4qmptj3z4kujjh2w5677xx3eizwydz2yao3nnrt7fhsnlq/1000.json");
            return ['action' => 'minted', 'txHash' => $txHash];
        } elseif (strtolower($currentOwner) === strtolower($buyer->address)) {
            $land->owner_id = $buyer->id;
            $land->fixed_price = 0;
            $land->save();
            return ['action' => 'database_updated', 'txHash' => null];
        } elseif (strtolower($approvedAddress) === strtolower($bankAddress)) {
            $txHash = $this->nftController->transferFrom($currentOwner, $buyer->address, $tokenId);
            return ['action' => 'transferred', 'txHash' => $txHash];
        } else {
            $blockchainOwner = User::whereRaw('LOWER(address) = ?', [strtolower($currentOwner)])->first();
            if ($blockchainOwner) {
                $land->owner_id = $blockchainOwner->id;
                $land->fixed_price = 0;
                $land->save();
                return ['action' => 'database_updated', 'txHash' => null];
            } else {
                throw new \Exception("Blockchain owner not found in database for land {$land->id}");
            }
        }
    }
    public function acceptBuy($landId): JsonResponse
    {
        try {
            $land = Land::findOrFail($landId);
            $buyer = Auth::user();
            if ($land->is_suspend) {
                return response()->json(['message' => 'This land is not for sale.'], 400);
            }
            DB::beginTransaction();
            try {
                $blockchainOwner = $this->nftController->getTokenOwner($land->id);
                \Log::info("Blockchain owner: $blockchainOwner");
                if ($blockchainOwner) {
                    $blockchainOwnerUser = User::whereRaw('LOWER(address) = ?', [strtolower($blockchainOwner)])->first();
                    \Log::info("Blockchain owner user: ", $blockchainOwnerUser ? $blockchainOwnerUser->toArray() : 'null');
                    if ($blockchainOwnerUser && $blockchainOwnerUser->id !== $land->owner_id) {
                        \Log::info("Updating land owner to match blockchain");
                        $land->owner_id = $blockchainOwnerUser->id;
                        $land->save();
                    }
                }
                $seller = User::findOrFail($land->owner_id);
                if (!$this->isLandApprovedForTransfer($land)) {
                    $land->fixed_price = 0;
                    $land->save();
                    \Log::info("Land fixed price set to 0");
                    DB::commit();
                    return response()->json(['error' => 'This land is not approved for transfer. The sale has been canceled.'], 400);
                }
                $result = $this->handleLandNFT($land, $buyer);
                if ($result['action'] !== 'database_updated') {
                    if (!$buyer->hasSufficientAsset('bnb', $land->fixed_price)) {
                        throw new \Exception('Insufficient BNB to purchase this land.');
                    }
                    $buyer->removeAsset('bnb', $land->fixed_price);
                }
                if ($result['txHash']) {
                    $land->nft_transaction_hash = $result['txHash'];
                }
               LandTransfer::createTransfer(
                    $land,
                    $seller,
                    $buyer,
                    'fixed_price',
                    'bnb',
                    $result['action'] === 'database_updated' ? 0 : $land->fixed_price
                );
                $buyer->offers()->where('land_id', $land->id)->delete();
                $land->offers()->where('user_id', '!=', $buyer->id)->delete();
                $land->transfer_times += 1;
                $land->owner_id = $buyer->id;
                $land->fixed_price = 0;
                $land->save();
                DB::commit();
                return response()->json([
                    'message' => 'Land purchased successfully.',
                    'land' => $land->fresh()->load('owner'),
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    public function acceptOffer($offerId): JsonResponse
    {
        try {
            $offer = Offer::findOrFail($offerId);
            $land = $offer->land;
            $buyer = $offer->user;
            $seller = $land->owner;
            if (!$this->isLandApprovedForTransfer($land)) {
                return response()->json(['error' => 'This land is not approved for transfer.'], 400);
            }
            DB::transaction(function () use ($offer, $land, $buyer, $seller) {
                $buyer->removeAsset($offer->price_asset_type, $offer->price);
                $result = $this->handleLandNFT($land, $buyer);
                if ($result['txHash']) {
                    $land->nft_transaction_hash = $result['txHash'];
                }
                LandTransfer::createTransfer(
                    $land,
                    $seller,
                    $buyer,
                    'offer',
                    $offer->price_asset_type,
                    $offer->price
                );
                $land->offers()->delete();
                $offer->delete();
                $land->transfer_times += 1;
                $land->save();
            });
            return response()->json([
                'message' => 'Offer accepted successfully.',
                'land' => $land->fresh()->load('owner'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing the offer: ' . $e->getMessage()], 500);
        }
    }
}
