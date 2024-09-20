<?php

namespace App\Http\Controllers;

use App\Models\Land;
use App\Models\LandCollection;
use DB;
use Illuminate\Http\Request;
use Log;

class AdminLandCollectionImportController extends Controller
{


    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'file_name' => 'required|string|max:255',
            'collection_name' => 'required|string|max:255',
            'type' => 'required|in:normal,mine',
        ]);

        $file = $request->file('file');
        $jsonContents = file_get_contents($file->path());
        $data = json_decode($jsonContents);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Invalid JSON format', [
                'error' => json_last_error_msg(),
                'file_name' => $request->file_name
            ]);
            return response()->json(['error' => 'Invalid JSON format: ' . json_last_error_msg()], 400);
        }

        $validationResult = $this->validateGeoJSON($data);
        if ($validationResult !== true) {
            Log::error('Invalid GeoJSON format', [
                'error' => $validationResult,
                'file_name' => $request->file_name
            ]);
            return response()->json(['error' => 'Invalid GeoJSON format: ' . $validationResult], 400);
        }

        try {
            DB::beginTransaction();

            $landCollection = LandCollection::create([
                'file_name' => $request->file_name,
                'collection_name' => $request->collection_name,
                'is_active' => true,
                'is_locked' => false,
                'type' => $request->type,
            ]);

     
            $createdLands = 0;
            $totalFeatures = count($data->features);

            foreach ($data->features as $index => $feature) {
                $this->processFeature($feature, $landCollection->id);
                $createdLands++;

             
            }

            DB::commit();

       

            return response()->json([
                'message' => 'Import completed',
                'lands_created' => $createdLands,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import failed', [
                'error' => $e->getMessage(),
                'file_name' => $request->file_name,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    private function processFeature($feature, $landCollectionId)
    {
        $coordinates = json_encode($feature->geometry);

        $land = Land::create([
            'geom' => DB::raw("ST_Multi(ST_GeomFromGeoJSON('$coordinates'))"),
            'centroid' => DB::raw("ST_Centroid(ST_GeomFromGeoJSON('$coordinates'))"),
            'size' => DB::raw("CAST(ST_Area(ST_GeomFromGeoJSON('$coordinates')::geography) AS INTEGER)"),
            'owner_id' => 1,
            'fixed_price' => 0,
            'is_locked' => false,
            'type' => 'normal',
            'land_collection_id' => $landCollectionId,
        ]);

        // Refresh to get the calculated values
        $land->refresh();

     

        return $land;
    }

    private function validateGeoJSON($data)
    {
        if (!isset($data->type) || $data->type !== 'FeatureCollection') {
            return "Missing or incorrect 'type' property";
        }
        if (!isset($data->features) || !is_array($data->features)) {
            return "Missing or invalid 'features' array";
        }
        if (!isset($data->name)) {
            return "Missing 'name' property";
        }
        if (!isset($data->crs) || !isset($data->crs->type) || !isset($data->crs->properties->name)) {
            return "Missing or invalid 'crs' property";
        }
        foreach ($data->features as $index => $feature) {
            if (!isset($feature->type) || $feature->type !== 'Feature') {
                return "Invalid feature type at index $index";
            }

            if (!isset($feature->properties) || !is_object($feature->properties)) {
                return "Missing or invalid 'properties' at feature index $index";
            }

            if (!isset($feature->geometry) || !is_object($feature->geometry)) {
                return "Missing or invalid 'geometry' at feature index $index";
            }

            if (!isset($feature->geometry->type) || !isset($feature->geometry->coordinates)) {
                return "Invalid geometry structure at feature index $index";
            }

            if ($feature->geometry->type !== 'MultiPolygon' && $feature->geometry->type !== 'Polygon') {
                return "Invalid geometry type at feature index $index. Expected 'MultiPolygon' or 'Polygon'";
            }
        }

        return true;
    }
}
