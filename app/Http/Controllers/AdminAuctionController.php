<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Land;
use DB;
use Illuminate\Http\Request;

class AdminAuctionController extends Controller
{
    public function bulkCreateAuctions(Request $request)
    {
        $validatedData = $request->validate([
            'landIds' => 'required|array',
            'landIds.*' => 'integer',
            'minimumPrice' => 'required|numeric',
            'startTime' => 'required|date',
            'endTime' => 'required|date|after:startTime',
        ]);

        DB::beginTransaction();

        try {
            $lands = Land::whereIn('id', $validatedData['landIds'])->get();

            foreach ($lands as $land) {
                Auction::create([
                    'land_id' => $land->id,
                    'owner_id' => $land->owner_id,
                    'minimum_price' => $validatedData['minimumPrice'],
                    'start_time' => $validatedData['startTime'],
                    'end_time' => $validatedData['endTime'],
                    'status' => 'active',
                ]);

                // Remove fixed price for this land
                $land->fixed_price = 0;
                $land->save();
            }

            DB::commit();
            return response()->json(['message' => 'Auctions created successfully and fixed prices removed']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create auctions: ' . $e->getMessage()], 500);
        }
    }
    public function bulkCancelAuctions(Request $request)
    {
        $validatedData = $request->validate([
            'auctionIds' => 'required|array',
            'auctionIds.*' => 'integer',
        ]);

        DB::beginTransaction();

        try {
            Auction::whereIn('id', $validatedData['auctionIds'])
                ->update(['status' => 'canceled']);

            DB::commit();
            return response()->json(['message' => 'Auctions canceled successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to cancel auctions: ' . $e->getMessage()], 500);
        }
    }


    public function bulkRemoveAuctions(Request $request)
    {
        $validatedData = $request->validate([
            'auctionIds' => 'required|array',
            'auctionIds.*' => 'integer',
        ]);

        DB::beginTransaction();

        try {
            Auction::whereIn('id', $validatedData['auctionIds'])->delete();

            DB::commit();
            return response()->json(['message' => 'Auctions removed successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to remove auctions: ' . $e->getMessage()], 500);
        }
    }

    public function getAuctions(Request $request)
    {
        $query = Auction::with('land');

        // Apply filters
        if ($request->filled('landId')) {
            $query->where('land_id', $request->landId);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('startDate')) {
            $query->where('start_time', '>=', $request->startDate);
        }
        if ($request->filled('endDate')) {
            $query->where('end_time', '<=', $request->endDate);
        }
        if ($request->filled('minPrice')) {
            $query->where('minimum_price', '>=', $request->minPrice);
        }
        if ($request->filled('maxPrice')) {
            $query->where('minimum_price', '<=', $request->maxPrice);
        }

        // Apply sorting
        $perPage = $request->filled('per_page') ? (int)$request->per_page : 10;
        $auctions = $query->paginate($perPage);

        // Paginate results
        $perPage = $request->filled('per_page') ? (int)$request->per_page : 10;
        $auctions = $query->paginate($perPage);

        return response()->json($auctions);
    }
}
