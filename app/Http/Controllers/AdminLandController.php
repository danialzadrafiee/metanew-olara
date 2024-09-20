<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Land;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminLandController extends Controller
{


    public function index(Request $request)
    {
        $query = Land::query();


        // Apply filters
        if ($request->boolean('filterForSale')) {
            $query->where('fixed_price', '>', 0);
        }
        if ($request->has('onlyBankLands')) {
            $query->where('owner_id', 1);
        }
        if ($request->has('selectedCollection')) {
            $query->where('land_collection_id', $request->input('selectedCollection'));
        }
        if ($request->has('searchTerm')) {
            $searchTerm = $request->input('searchTerm');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('id', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('owner', function ($query) use ($searchTerm) {
                        $query->where('nickname', 'like', '%' . $searchTerm . '%');
                    });
            });
        }
        // Apply sorting
        $sortBy = $request->input('sortBy', 'id');
        $sortOrder = $request->input('sortOrder', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $request->input('perPage', 12);
        $page = $request->input('page', 1);
        $total = $query->count();

        $results = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        // Exclude specific fields
        $excludedFields = ['coordinates', 'centroid', 'geom', 'owner'];
        $filteredLands = $results->map(function ($land) use ($excludedFields) {
            return collect($land->toArray())
                ->except($excludedFields)
                ->all();
        });

        // Create a new paginator instance with the filtered data
        $paginator = new LengthAwarePaginator(
            $filteredLands,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return response()->json($paginator);
    }

    public function getAllLandIds()
    {
        $landIds = Land::pluck('id')->toArray();
        return response()->json($landIds);
    }


    public function getFilteredLandIds(Request $request)
    {
        $query = Land::query();

        // Apply the same filters as in the index method
        if ($request->boolean('filterForSale')) {
            $query->where('fixed_price', '>', 0);
        }
        if ($request->boolean('onlyBankLands')) {
            $query->where('owner_id', 1);
        }
        if ($request->has('selectedCollection')) {
            $query->where('land_collection_id', $request->input('selectedCollection'));
        }
        if ($request->has('searchTerm')) {
            $searchTerm = $request->input('searchTerm');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('id', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('owner', function ($query) use ($searchTerm) {
                        $query->where('nickname', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        $landIds = $query->pluck('id')->toArray();
        return response()->json($landIds);
    }


    public function bulkUpdateFixedPrice(Request $request)
    {
        $validatedData = $request->validate([
            'landIds' => 'required|array',
            'fixedPrice' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $lands = Land::whereIn('id', $validatedData['landIds'])->get();

            foreach ($lands as $land) {
                $land->fixed_price = $validatedData['fixedPrice'];
                $land->save();

                // Cancel any active auctions for this land
                Auction::where('land_id', $land->id)
                    ->where('status', 'active')
                    ->update(['status' => 'canceled']);
            }

            DB::commit();
            return response()->json(['message' => 'Lands updated successfully with fixed price and auctions canceled']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update lands: ' . $e->getMessage()], 500);
        }
    }

    public function bulkUpdatePriceBySize(Request $request)
    {
        $validatedData = $request->validate([
            'landIds' => 'required|array',
            'pricePerSize' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $lands = Land::whereIn('id', $validatedData['landIds'])->get();

            foreach ($lands as $land) {
                $land->fixed_price = $land->size * $validatedData['pricePerSize'];
                $land->save();

                // Cancel any active auctions for this land
                Auction::where('land_id', $land->id)
                    ->where('status', 'active')
                    ->update(['status' => 'canceled']);
            }

            DB::commit();
            return response()->json(['message' => 'Lands updated successfully with price by size and auctions canceled']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update lands: ' . $e->getMessage()], 500);
        }
    }
}
