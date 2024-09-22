<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Land;
use App\Models\LandTransfer;
use App\Models\User;
use App\Models\Offer;
use App\Models\ScratchBox;
use App\Traits\AuctionTrait;
use App\Traits\LandNFTTrait;
use App\Traits\ScratchBoxTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LandTransferController extends Controller
{
    use LandNFTTrait, AuctionTrait, ScratchBoxTrait;

    protected $nftController;

    public function __construct(NftController $nftController)
    {
        $this->nftController = $nftController;
    }

    public function acceptBuy($landId): JsonResponse
    {
        try {
            $land = Land::findOrFail($landId);
            $buyer = Auth::user();
            $seller = User::findOrFail($land->owner_id);

            if ($land->is_suspend) {
                return response()->json(['message' => 'This land is not for sale.'], 400);
            }

            if (!$this->isLandApprovedForTransfer($land)) {
                $land->update(['fixed_price' => 0]);
                return response()->json(['error' => 'This land is not approved for transfer. The sale has been canceled.'], 400);
            }

            if (!$buyer->hasSufficientAsset('bnb', $land->fixed_price)) {
                return response()->json(['error' => 'Insufficient funds to purchase this land.'], 400);
            }

            $result = $this->handleLandNFT($land, $buyer);
            if ($result['txHash']) {
                $land->last_nft_transaction_hash = $result['txHash'];
            }

            $updatedLand = LandTransfer::createTransfer(
                $land,
                $seller,
                $buyer,
                'fixed_price',
                'bnb',
                $land->fixed_price
            );

            return response()->json([
                'message' => 'Land purchased successfully.',
                'land' => $updatedLand,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function acceptOffer($offerId): JsonResponse
    {
        try {
            Log::info("acceptOffer started for offer ID: $offerId");

            $offer = Offer::findOrFail($offerId);
            $land = $offer->land;
            $buyer = $offer->user;
            $seller = $land->owner;

            Log::info("Offer details:", [
                'land_id' => $land->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'price' => $offer->price,
                'asset_type' => $offer->price_asset_type
            ]);

            if (!$this->isLandApprovedForTransfer($land)) {
                Log::warning("Land not approved for transfer", ['land_id' => $land->id]);
                return response()->json(['error' => 'This land is not approved for transfer.'], 400);
            }

            DB::transaction(function () use ($offer, $land, $buyer, $seller) {
                Log::info("Starting transaction for offer acceptance");

                $result = $this->handleLandNFT($land, $buyer);
                if ($result['txHash']) {
                    $land->last_nft_transaction_hash = $result['txHash'];
                    Log::info("NFT transfer completed", ['txHash' => $result['txHash']]);
                }

                $updatedLand = LandTransfer::createTransfer(
                    $land,
                    $seller,
                    $buyer,
                    'offer',
                    $offer->price_asset_type,
                    $offer->price,
                    $offer
                );

                Log::info("LandTransfer created", ['transfer_id' => $updatedLand->id]);
            });

            Log::info("Offer acceptance completed successfully");

            return response()->json([
                'message' => 'Offer accepted successfully.',
                'land' => $land->fresh()->load('owner'),
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error in acceptOffer: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'An error occurred while processing the offer: ' . $e->getMessage()], 500);
        }
    }

    public function executeSingleAuction($landId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $land = Land::findOrFail($landId);
            $activeAuctions = $land->auctions()->where('status', 'active')->orderBy('created_at', 'desc')->get();

            if ($activeAuctions->isEmpty()) {
                return response()->json([
                    'message' => 'No active auctions found for this land.',
                    'land_id' => $landId,
                    'explanation' => 'The specified land does not have any active auctions to execute.'
                ], 404);
            }

            $newestAuction = $activeAuctions->first();
            $response = [
                'message' => 'Auction execution completed.',
                'land_id' => $landId,
                'newest_auction_id' => $newestAuction->id,
                'canceled_auctions' => [],
                'newest_auction_status' => 'unchanged',
                'explanation' => ''
            ];

            // Cancel older auctions
            foreach ($activeAuctions as $auction) {
                if ($auction->id !== $newestAuction->id) {
                    $canceledBids = $this->cancelAuction($auction);
                    $response['canceled_auctions'][] = [
                        'auction_id' => $auction->id,
                        'canceled_bids' => $canceledBids
                    ];
                }
            }

            // Process the newest auction
            $auctionResult = $this->finalizeAuction($newestAuction);
            $response['newest_auction_status'] = 'finalized';
            $response['auction_result'] = $auctionResult;

            // Prepare explanation
            $response['explanation'] = $this->prepareExplanation($response);

            DB::commit();
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error executing auction for land {$landId}: " . $e->getMessage());
            return response()->json([
                'error' => 'An error occurred while executing the auction.',
                'message' => $e->getMessage(),
                'land_id' => $landId
            ], 500);
        }
    }


    public function executeAllAuctions(): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Get all lands with active auctions
            $landsWithActiveAuctions = Land::whereHas('auctions', function ($query) {
                $query->where('status', 'active');
            })->get();

            $results = [];

            foreach ($landsWithActiveAuctions as $land) {
                // Execute auction for each land
                $auctionResult = $this->executeSingleAuction($land->id);
                $results[] = [
                    'land_id' => $land->id,
                    'result' => $auctionResult->original // Assuming executeSingleAuction returns a JsonResponse
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'All active auctions have been executed.',
                'total_processed' => count($results),
                'results' => $results
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error executing all auctions: " . $e->getMessage());
            return response()->json([
                'error' => 'An error occurred while executing all auctions.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function openScratchBox($scratchBoxId): JsonResponse
    {
        $user = Auth::user();

        DB::beginTransaction();
        try {
            $scratchBox = ScratchBox::lockForUpdate()->findOrFail($scratchBoxId);

            if ($scratchBox->status === 'opened') {
                throw new \Exception('This scratch box has already been opened.');
            }

            $scratchBoxAsset = $user->assets()->where('type', 'scratch_box')->lockForUpdate()->firstOrFail();

            if ($scratchBoxAsset->amount <= 0) {
                throw new \Exception('You do not have any scratch boxes available to open.');
            }

            $result = $this->processLands($scratchBox->lands, $user);

            $scratchBoxAsset->decrement('amount');

            if ($result['refundAmount'] > 0) {
                $user->addAsset('bnb', $result['refundAmount']);
            }

            $scratchBox->update(['status' => 'opened']);

            DB::commit();

            Log::info("Scratch box {$scratchBoxId} opened by user {$user->id}. Transferred: " . count($result['transferred']) . ", Failed: " . count($result['failed']) . ", Refunded: {$result['refundAmount']} BNB");

            return response()->json([
                'message' => 'Scratch box opened successfully.',
                'lands' => $result['transferred'],
                'failed_lands' => $result['failed'],
                'refund_amount' => $result['refundAmount']
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to open scratch box {$scratchBoxId} for user {$user->id}: " . $e->getMessage());
            return response()->json(['error' => 'Failed to open scratch box: ' . $e->getMessage()], 500);
        }
    }
}
