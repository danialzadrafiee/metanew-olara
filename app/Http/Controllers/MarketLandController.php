<?php

namespace App\Http\Controllers;

use App\Models\Land;
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
        $showOnlyForSale = $request->boolean('for_sale', false); 
        $search = $request->input('search', ''); 

        $query = Land::with('owner:id,nickname');

        if ($userLandsOnly) {
            $user = Auth::user();
            $query->where('owner_id', $user->id);
        }

        if ($showOnlyForSale) {
            $query->whereNotNull('fixed_price')->where('fixed_price', '>', 0);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->Where('region', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }
        
        $query->orderBy($sortBy, $sortOrder);
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
