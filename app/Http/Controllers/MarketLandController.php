<?php

namespace App\Http\Controllers;

use App\Models\Land;
use App\Models\Auction;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketLandController extends Controller
{
    public function getMarketplaceLands(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $sortBy = $request->input('sort_by', 'fixed_price');
        $sortOrder = $request->input('sort_order', 'asc');
        $userLandsOnly = $request->boolean('user_lands_only', false);
        $saleStatus = $request->input('sale_status', 'all');
        $search = $request->input('search', '');

        $query = Land::with('owner:id,nickname');

        if ($userLandsOnly) {
            $user = Auth::user();
            $query->where('owner_id', $user->id);
        }

        // New filter logic
        if ($saleStatus === 'for_sale') {
            $query->whereNotNull('fixed_price')->where('fixed_price', '>', 0);
        } elseif ($saleStatus === 'in_auction') {
            $query->whereHas('activeAuction');
        } elseif ($saleStatus === 'for_sale_or_auction') {
            $query->where(function ($q) {
                $q->whereNotNull('fixed_price')->where('fixed_price', '>', 0)
                    ->orWhereHas('activeAuction');
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%");
            });
        }

        if ($sortBy === 'fixed_price') {
            $query->orderByRaw('COALESCE(fixed_price, 0) ' . $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }
        $query->orderBy('id', 'asc');

        $lands = $query->paginate($perPage);

        return response()->json([
            'data' => $lands->items(),
            'current_page' => $lands->currentPage(),
            'last_page' => $lands->lastPage(),
            'per_page' => $lands->perPage(),
            'total' => $lands->total(),
        ]);
    }
}
