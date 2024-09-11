<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CronController extends Controller
{
    public function processAllAuctions(): JsonResponse
    {
        $endedAuctions = Auction::where('status', 'active')
            ->where('end_time', '<=', now())
            ->with(['land', 'bids.user'])
            ->get();

        $processedCount = 0;

        foreach ($endedAuctions as $auction) {
            if ($auction->processAuction()) {
                $processedCount++;
            }
        }

        $canceledAuctions = Auction::where('status', 'canceled')
            ->where('end_time', '<=', now())
            ->with('land')
            ->get();

        foreach ($canceledAuctions as $canceledAuction) {
            if (Auction::processCanceledAuction($canceledAuction)) {
                $processedCount++;
            }
        }

        return response()->json([
            'message' => 'Auctions processed successfully',
            'processed_count' => $processedCount,
            'ended_auctions_count' => $endedAuctions->count(),
            'canceled_auctions_count' => $canceledAuctions->count(),
        ]);
    }

    public function forceAllProcessAllAuctions(): JsonResponse
    {
        $activeAuctions = Auction::where('status', 'active')
            ->with(['land', 'bids.user'])
            ->get();

        $processedCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($activeAuctions as $auction) {
            $result = $auction->processAuction();
            if ($result) {
                $processedCount++;
                $results[] = [
                    'auction_id' => $auction->id,
                    'status' => $auction->highestBid ? 'Sold' : 'No bids',
                ];
            } else {
                $failedCount++;
                $results[] = [
                    'auction_id' => $auction->id,
                    'status' => 'Failed',
                    'error' => 'Failed to process auction',
                ];
            }
        }

        return response()->json([
            'message' => 'All active auctions force processed',
            'total_auctions' => $activeAuctions->count(),
            'processed_count' => $processedCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ]);
    }
}