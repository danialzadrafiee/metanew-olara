<?php

namespace App\Http\Controllers;

use App\Models\InactiveLand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Land;
use App\Models\LandCollection;
use Log;

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
            DB::beginTransaction();

            $collection = LandCollection::findOrFail($id);
            $landsCount = $collection->lands()->count();
            $collection->lands()->delete();
            $collection->delete();
            DB::commit();
            Log::info('Collection and associated lands deleted successfully', [
                'collection_id' => $id,
                'lands_deleted' => $landsCount
            ]);
            return response()->json(['message' => 'Collection and associated lands deleted successfully'], 200);
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
        Log::info('Updating active collections', ['active_collections' => $request->active_collections]);
        $request->validate([
            'active_collections' => 'required|array',
            'active_collections.*' => 'integer|exists:land_collections,id',
        ]);
        DB::beginTransaction();
        try {
            LandCollection::query()->update(['is_active' => false]);
            LandCollection::whereIn('id', $request->active_collections)->update(['is_active' => true]);
            DB::commit();
            Log::info('Active collections updated successfully', [
                'active_collections' => $request->active_collections
            ]);
            return response()->json(['message' => 'Active collections updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
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
        DB::beginTransaction();

        try {
            $collection = LandCollection::findOrFail($collectionId);
            $landsCount = $collection->lands()->count();
            $collection->lands()->update(['is_locked' => true]);
            $collection->is_locked = true;
            $collection->save();

            DB::commit();
            Log::info('Lands locked successfully', [
                'collection_id' => $collectionId,
                'lands_locked' => $landsCount
            ]);
            return response()->json(['message' => 'Lands locked successfully'], 200);
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
        Log::info('Attempting to unlock lands', ['collection_id' => $collectionId]);
        DB::beginTransaction();
    
        try {
            $collection = LandCollection::findOrFail($collectionId);
    
            // Check if any land in the collection is in a scratch box
            $landInScratchBox = $collection->lands()
                ->whereHas('scratchBoxes', function ($query) {
                    $query->where('status', '!=', 'opened');
                })
                ->with('scratchBoxes')
                ->first();
    
            if ($landInScratchBox) {
                $scratchBox = $landInScratchBox->scratchBoxes->first();
                DB::rollBack();
                return response()->json([
                    'error' => 'This collection cannot be unlocked.',
                    'reason' => 'Land is in a scratch box',
                    'scratch_box' => [
                        'id' => $scratchBox->id,
                        'name' => $scratchBox->name // Assuming the scratch box has a 'name' attribute
                    ]
                ], 400);
            }
    
            $landsCount = $collection->lands()->count();
            $collection->lands()->update(['is_locked' => false]);
            $collection->is_locked = false;
            $collection->save();
    
            DB::commit();
            Log::info('Lands unlocked successfully', [
                'collection_id' => $collectionId,
                'lands_unlocked' => $landsCount
            ]);
            return response()->json(['message' => 'Lands unlocked successfully'], 200);
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
        Log::info('Attempting to toggle active status', ['collection_id' => $id]);
        try {
            DB::beginTransaction();
    
            $collection = LandCollection::lockForUpdate()->findOrFail($id);
            $newActiveStatus = !$collection->is_active;
    
            if ($newActiveStatus) {
                // Activating the collection
                InactiveLand::where('land_collection_id', $collection->id)
                    ->chunkById(1000, function ($lands) {
                        foreach ($lands as $land) {
                            Land::create($land->getAttributes());
                            $land->delete();
                        }
                    });
            } else {
                // Deactivating the collection
                $collection->lands()
                    ->chunkById(1000, function ($lands) {
                        foreach ($lands as $land) {
                            InactiveLand::create($land->getAttributes());
                            $land->delete();
                        }
                    });
            }
    
            $collection->update(['is_active' => $newActiveStatus]);
    
            DB::commit();
    
            Log::info('Collection active status toggled successfully', [
                'collection_id' => $id,
                'is_active' => $newActiveStatus
            ]);
    
            return response()->json([
                'message' => 'Collection active status toggled successfully',
                'is_active' => $newActiveStatus
            ], 200);
    
        } catch (\Exception $e) {
            DB::rollBack();
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

        DB::beginTransaction();

        try {
            $collection = LandCollection::findOrFail($id);
            $collection->type = $request->type;
            $collection->save();

            $collection->lands()->update(['type' => $request->type]);

            DB::commit();
            Log::info('Land type updated successfully', [
                'collection_id' => $id,
                'new_type' => $request->type
            ]);
            return response()->json(['message' => 'Land type updated successfully'], 200);
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
