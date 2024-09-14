<?php

namespace App\Http\Controllers;

use App\Models\Land;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function setLandBuildingId(Request $request): JsonResponse
    {
        $user = Auth::user();
        $request->validate([
            'land_id' => 'required|exists:lands,id',
            'building_id' => 'required'
        ]);


        $land = Land::findOrFail($request->land_id);
        $land->building_id = $request->building_id;
        $land->building_name = "Building " . $request->building_id; // Optional: Set a name for the building
        $land->save();

        $user->removeAsset('wood', 10);
        $user->removeAsset('sand', 3);


        return response()->json($land, 200);
    }
}
