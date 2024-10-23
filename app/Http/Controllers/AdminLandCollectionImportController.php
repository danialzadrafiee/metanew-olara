<?php

namespace App\Http\Controllers;

use App\Models\LandCollection;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminLandCollectionImportController extends Controller
{
    const BATCH_SIZE = 1000;
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'file_name' => 'required|string|max:255',
            'region'=> 'sometimes|string|max:255', 
            'city' => 'sometimes|string|max:255',
            'collection_name' => 'required|string|max:255',
            'type' => 'required|in:normal,mine',
        ]);

        $file = $request->file('file');
        $jsonContents = file_get_contents($file->path());
        $data = json_decode($jsonContents);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::info('Invalid JSON format', [
                'error' => json_last_error_msg(),
                'file_name' => $request->file_name
            ]);
            return response()->json(['error' => 'Invalid JSON format: ' . json_last_error_msg()], 400);
        }

        $validationResult = $this->validateGeoJSON($data);
        if ($validationResult !== true) {
            Log::info('Invalid GeoJSON format', [
                'error' => $validationResult,
                'file_name' => $request->file_name
            ]);
            return response()->json(['error' => 'Invalid GeoJSON format: ' . $validationResult], 400);
        }

        try {
            DB::beginTransaction();

            Log::info("Starting import", [
                'file_name' => $request->file_name,
                'collection_name' => $request->collection_name
            ]);

            $landCollection = LandCollection::create([
                'file_name' => $request->file_name,
                'collection_name' => $request->collection_name,
                'is_active' => true,
                'region' => $request->region,
                'city' => $request->city,
                'is_locked' => false,
                'type' => $request->type,
            ]);

            $totalFeatures = count($data->features);
            $createdLands = $this->processFeaturesInBatches($data->features, $landCollection->id, $totalFeatures);

            DB::commit();

            Log::info("Import completed", [
                'total_processed' => $createdLands,
                'collection_id' => $landCollection->id
            ]);

            return response()->json([
                'message' => 'Import completed',
                'lands_created' => $createdLands,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::info('Import failed', [
                'error' => $e->getMessage(),
                'file_name' => $request->file_name,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    private function processFeaturesInBatches($features, $landCollectionId, $totalFeatures)
    {
        $createdLands = 0;
        $batch = [];
        $batchParams = [];
        $logInterval = max(1, ceil($totalFeatures * 0.005));

        // Prepare the SQL template
        $sqlTemplate = "INSERT INTO lands (geom, centroid, size, owner_id, fixed_price, is_locked, type, land_collection_id, created_at, updated_at) VALUES ";
        $now = now()->format('Y-m-d H:i:s');

        foreach ($features as $index => $feature) {
            $coordinates = json_encode($feature->geometry);
            
            $batch[] = "(ST_Multi(ST_GeomFromGeoJSON(?)), ST_Centroid(ST_GeomFromGeoJSON(?)), CAST(ST_Area(ST_GeomFromGeoJSON(?)::geography) AS INTEGER), ?, ?, ?, ?, ?, ?, ?)";
            $batchParams[] = $coordinates;
            $batchParams[] = $coordinates;
            $batchParams[] = $coordinates;
            $batchParams[] = 1; // owner_id
            $batchParams[] = 0; // fixed_price
            $batchParams[] = false; // is_locked
            $batchParams[] = 'normal'; // type
            $batchParams[] = $landCollectionId;
            $batchParams[] = $now;
            $batchParams[] = $now;

            if (count($batch) >= self::BATCH_SIZE || $index === count($features) - 1) {
                // Execute batch insert
                $sql = $sqlTemplate . implode(',', $batch);
                DB::statement($sql, $batchParams);

                $createdLands += count($batch);
                
                if ($createdLands % $logInterval === 0 || $createdLands === $totalFeatures) {
                    $progress = round(($createdLands / $totalFeatures) * 100, 2);
                    Log::info("Progress: {$progress}%", [
                        'processed' => $createdLands,
                        'total' => $totalFeatures,
                        'collection_id' => $landCollectionId
                    ]);
                }
                $batch = [];
                $batchParams = [];
            }
        }

        return $createdLands;
    }

    private function validateGeoJSON($data)
    {
        Log::info("Starting GeoJSON validation");

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

        Log::info("GeoJSON validation completed successfully");
        return true;
    }
}