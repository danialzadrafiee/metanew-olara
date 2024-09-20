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
            return response()->json(['message' => 'No collections found']);
        }

        return response()->json($collections);
    }

    public function getCollection($id)
    {

        $collection = LandCollection::with('lands')->findOrFail($id);

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
        $request->validate([
            'active_collections' => 'required|array',
            'active_collections.*' => 'exists:land_collections,id',
        ]);

        try {
            $result = LandCollection::updateActiveCollections($request->active_collections);
            if ($result) {

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
        try {
            $collection = LandCollection::findOrFail($collectionId);
            if ($collection->lockLands()) {

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
        try {
            $collection = LandCollection::findOrFail($id);
            if ($collection->toggleActive()) {

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
        $request->validate([
            'type' => 'required|in:normal,mine',
        ]);

        try {
            $collection = LandCollection::findOrFail($id);
            if ($collection->updateLandType($request->type)) {

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
