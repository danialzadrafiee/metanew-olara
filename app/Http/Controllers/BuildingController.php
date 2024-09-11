<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Land;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BuildingController extends Controller
{
    public function setLandBuildingId(Request $request): JsonResponse
    {
        $request->validate([
            'land_id' => 'required|exists:lands,id',
            'building_id' => 'required|integer'
        ]);

        $land = Land::findOrFail($request->land_id);
        $land->building_id = $request->building_id;
        $land->building_name = "Building " . $request->building_id; // Optional: Set a name for the building
        $land->save();

        return response()->json($land, 200);
    }
}
