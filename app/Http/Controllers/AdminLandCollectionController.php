<?php

namespace App\Http\Controllers;

use App\Models\LandCollection;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminLandCollectionController extends Controller
{
    const BATCH_SIZE = 1000;


    public function getCollections()
    {
        $collections = LandCollection::withCount('lands')->orderBy('created_at', 'desc')->get();

        if ($collections->isEmpty()) {
            return response()->json(['message' => 'No collections found']);
        }

        return response()->json($collections);
    }
    
    public function getCollection($id)
    {
        $collection = LandCollection::with(['lands' => function($query) {
            $query->select('id', 'land_collection_id'); // Select only needed fields
        }])->findOrFail($id);

        return response()->json($collection);
    }


    public function deleteCollection($id)
    {
        try {
            $collection = LandCollection::findOrFail($id);

            if ($collection->contain_sold_land) {
                Log::warning('Deletion attempt failed: Collection contains sold land', ['collection_id' => $id]);
                return response()->json(['error' => 'Cannot delete collection containing sold land'], 400);
            }

            DB::beginTransaction();

            // Get total count for progress tracking
            $totalLands = $collection->lands()->count();
            $deletedCount = 0;
            $logInterval = max(1, ceil($totalLands * 0.1)); // Log every 10%

            // Delete lands in batches
            while (true) {
                $landIds = $collection->lands()
                    ->select('id')
                    ->limit(self::BATCH_SIZE)
                    ->pluck('id');

                if ($landIds->isEmpty()) {
                    break;
                }

                $batchDeleteCount = $collection->lands()
                    ->whereIn('id', $landIds)
                    ->delete();

                $deletedCount += $batchDeleteCount;

                if ($deletedCount % $logInterval === 0 || $deletedCount === $totalLands) {
                    $progress = round(($deletedCount / $totalLands) * 100, 2);
                    Log::info("Deleting lands progress: {$progress}%", [
                        'collection_id' => $id,
                        'processed' => $deletedCount,
                        'total' => $totalLands
                    ]);
                }
            }

            // Delete the collection
            $collection->delete();

            DB::commit();

            return response()->json([
                'message' => 'Collection and associated lands deleted successfully',
                'deleted_lands_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete failed', [
                'collection_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }

    public function updateActiveCollections(Request $request)
    {
        $request->validate([
            'active_collections' => 'required|array',
            'active_collections.*' => 'exists:land_collections,id',
        ]);

        try {
            $result = LandCollection::updateActiveCollections($request->active_collections);
            if ($result) {

                return response()->json(['message' => 'Active collections updated successfully'], 200);
            } else {
                return response()->json(['error' => 'Update operation failed'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Update active collections failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function lockLands($collectionId)
    {
        try {
            $collection = LandCollection::findOrFail($collectionId);
            
            DB::beginTransaction();

            $totalLands = $collection->lands()->count();
            $processedCount = 0;
            $logInterval = max(1, ceil($totalLands * 0.1));

            // Process in batches
            while (true) {
                $lands = $collection->lands()
                    ->where('is_locked', false)
                    ->limit(self::BATCH_SIZE)
                    ->pluck('id');

                if ($lands->isEmpty()) {
                    break;
                }

                $updateCount = $collection->lands()
                    ->whereIn('id', $lands)
                    ->update(['is_locked' => true]);

                $processedCount += $updateCount;

                if ($processedCount % $logInterval === 0 || $processedCount === $totalLands) {
                    $progress = round(($processedCount / $totalLands) * 100, 2);
                    Log::info("Locking lands progress: {$progress}%", [
                        'collection_id' => $collectionId,
                        'processed' => $processedCount,
                        'total' => $totalLands
                    ]);
                }
            }

            $collection->is_locked = true;
            $collection->save();

            DB::commit();

            return response()->json([
                'message' => 'Lands locked successfully',
                'processed_count' => $processedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lock lands failed', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Lock failed: ' . $e->getMessage()], 500);
        }
    }

    public function unlockLands($collectionId)
    {
        try {
            $collection = LandCollection::findOrFail($collectionId);

            // Check for scratch box condition first
            if ($collection->type === 'scratch_box') {
                return response()->json([
                    'error' => 'This collection cannot be unlocked.',
                    'reason' => 'Land is in a scratch box'
                ], 400);
            }

            DB::beginTransaction();

            $totalLands = $collection->lands()->count();
            $processedCount = 0;
            $logInterval = max(1, ceil($totalLands * 0.1));

            // Process in batches
            while (true) {
                $lands = $collection->lands()
                    ->where('is_locked', true)
                    ->limit(self::BATCH_SIZE)
                    ->pluck('id');

                if ($lands->isEmpty()) {
                    break;
                }

                $updateCount = $collection->lands()
                    ->whereIn('id', $lands)
                    ->update(['is_locked' => false]);

                $processedCount += $updateCount;

                if ($processedCount % $logInterval === 0 || $processedCount === $totalLands) {
                    $progress = round(($processedCount / $totalLands) * 100, 2);
                    Log::info("Unlocking lands progress: {$progress}%", [
                        'collection_id' => $collectionId,
                        'processed' => $processedCount,
                        'total' => $totalLands
                    ]);
                }
            }

            $collection->is_locked = false;
            $collection->save();

            DB::commit();

            return response()->json([
                'message' => 'Lands unlocked successfully',
                'processed_count' => $processedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Unlock lands failed', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Unlock failed: ' . $e->getMessage()], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            $collection = LandCollection::findOrFail($id);
            if ($collection->toggleActive()) {

                return response()->json([
                    'message' => 'Collection active status toggled successfully',
                    'is_active' => $collection->is_active
                ], 200);
            } else {
                return response()->json(['error' => 'Toggle operation failed'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Toggle active status failed', [
                'collection_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Toggle failed: ' . $e->getMessage()], 500);
        }
    }

    public function updateLandType(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:normal,mine',
        ]);

        try {
            $collection = LandCollection::findOrFail($id);
            
            DB::beginTransaction();

            $totalLands = $collection->lands()->count();
            $processedCount = 0;
            $logInterval = max(1, ceil($totalLands * 0.1));

            // Process in batches
            while (true) {
                $lands = $collection->lands()
                    ->where('type', '!=', $request->type)
                    ->limit(self::BATCH_SIZE)
                    ->pluck('id');

                if ($lands->isEmpty()) {
                    break;
                }

                $updateCount = $collection->lands()
                    ->whereIn('id', $lands)
                    ->update(['type' => $request->type]);

                $processedCount += $updateCount;

                if ($processedCount % $logInterval === 0 || $processedCount === $totalLands) {
                    $progress = round(($processedCount / $totalLands) * 100, 2);
                    Log::info("Updating land type progress: {$progress}%", [
                        'collection_id' => $id,
                        'type' => $request->type,
                        'processed' => $processedCount,
                        'total' => $totalLands
                    ]);
                }
            }

            $collection->type = $request->type;
            $collection->save();

            DB::commit();

            return response()->json([
                'message' => 'Land type updated successfully',
                'processed_count' => $processedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update land type failed', [
                'collection_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }
}
