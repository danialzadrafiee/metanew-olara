<?php

namespace App\Http\Controllers;

use App\Models\LandCollection;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminLandCollectionController extends Controller
{
    public function getCollections()
    {
        $collections = LandCollection::withCount('lands')->orderBy('created_at', 'desc')->get();

        if ($collections->isEmpty()) {
            Log::info('No collections found');
            return response()->json(['message' => 'No collections found']);
        }

        return response()->json($collections);
    }

    public function getCollection($id)
    {
        Log::info('Fetching land collection', ['collection_id' => $id]);
        $collection = LandCollection::with('lands')->findOrFail($id);
        Log::info('Collection fetched successfully', [
            'collection_id' => $collection->id,
            'lands_count' => $collection->lands->count()
        ]);
        return response()->json($collection);
    }

    public function deleteCollection($id)
    {
        Log::info('Attempting to delete land collection', ['collection_id' => $id]);
        try {
            $collection = LandCollection::findOrFail($id);
            
            if ($collection->contain_sold_land) {
                Log::warning('Deletion attempt failed: Collection contains sold land', ['collection_id' => $id]);
                return response()->json(['error' => 'Cannot delete collection containing sold land'], 400);
            }
            
            // Start a database transaction
            DB::beginTransaction();
            
            // Delete associated lands
            $landsCount = $collection->lands()->count();
            $deletedLandsCount = $collection->lands()->delete();
            
            if ($deletedLandsCount !== $landsCount) {
                throw new \Exception('Failed to delete all associated lands');
            }
            
            // Delete the collection
            if (!$collection->delete()) {
                throw new \Exception('Failed to delete the collection');
            }
            
            // Commit the transaction
            DB::commit();
            
            Log::info('Collection and associated lands deleted successfully', [
                'collection_id' => $id,
                'lands_deleted' => $deletedLandsCount
            ]);
            
            return response()->json(['message' => 'Collection and associated lands deleted successfully'], 200);
            
        } catch (\Exception $e) {
            // Rollback the transaction in case of any error
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
        Log::info('Updating active collections', ['active_collections' => $request->active_collections]);
        $request->validate([
            'active_collections' => 'required|array',
            'active_collections.*' => 'unsignedBigInteger|exists:land_collections,id',
        ]);

        try {
            $result = LandCollection::updateActiveCollections($request->active_collections);
            if ($result) {
                Log::info('Active collections updated successfully', [
                    'active_collections' => $request->active_collections
                ]);
                return response()->json(['message' => 'Active collections updated successfully'], 200);
            } else {
                throw new \Exception('Update operation failed');
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
        Log::info('Attempting to lock lands', ['collection_id' => $collectionId]);
        try {
            $collection = LandCollection::findOrFail($collectionId);
            if ($collection->lockLands()) {
                Log::info('Lands locked successfully', [
                    'collection_id' => $collectionId,
                    'lands_locked' => $collection->lands()->count()
                ]);
                return response()->json(['message' => 'Lands locked successfully'], 200);
            } else {
                throw new \Exception('Lock operation failed');
            }
        } catch (\Exception $e) {
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
        Log::info('Attempting to unlock lands', ['collection_id' => $collectionId]);
        try {
            $collection = LandCollection::findOrFail($collectionId);
            $result = $collection->unlockLands();
            if ($result === true) {
                return response()->json(['message' => 'Lands unlocked successfully'], 200);
            } elseif ($result === 'scratch_box') {
                return response()->json([
                    'error' => 'This collection cannot be unlocked.',
                    'reason' => 'Land is in a scratch box'
                ], 400);
            } else {
                Log::error('Unlock operation failed with unknown result', ['result' => $result]);
                throw new \Exception('Unlock operation failed');
            }
        } catch (\Exception $e) {
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
        Log::info('Attempting to toggle active status', ['collection_id' => $id]);
        try {
            $collection = LandCollection::findOrFail($id);
            if ($collection->toggleActive()) {
                Log::info('Collection active status toggled successfully', [
                    'collection_id' => $id,
                    'is_active' => $collection->is_active
                ]);
                return response()->json([
                    'message' => 'Collection active status toggled successfully',
                    'is_active' => $collection->is_active
                ], 200);
            } else {
                throw new \Exception('Toggle operation failed');
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
        Log::info('Attempting to update land type', ['collection_id' => $id, 'new_type' => $request->type]);
        $request->validate([
            'type' => 'required|in:normal,mine',
        ]);

        try {
            $collection = LandCollection::findOrFail($id);
            if ($collection->updateLandType($request->type)) {
                Log::info('Land type updated successfully', [
                    'collection_id' => $id,
                    'new_type' => $request->type
                ]);
                return response()->json(['message' => 'Land type updated successfully'], 200);
            } else {
                throw new \Exception('Update operation failed');
            }
        } catch (\Exception $e) {
            Log::error('Update land type failed', [
                'collection_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }
}
