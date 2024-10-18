<?php

namespace App\Http\Controllers;

use App\Models\Land;
use App\Models\ScratchBox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminScratchBoxController extends Controller
{
    public function index()
    {
        try {
            $scratchBoxes = ScratchBox::with('lands')->paginate(10);
            return response()->json($scratchBoxes);
        } catch (\Exception $e) {
            Log::error('Failed to fetch scratch boxes: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch scratch boxes'], 500);
        }
    }


    public function getRandomLands(Request $request)
    {
        try {
            $request->validate([
                'count' => 'required|integer|min:1',
            ]);
    
            $count = $request->input('count');
    
            $randomLands = Land::where('is_locked', true)
                ->where('is_in_scratch', false)
                ->inRandomOrder()
                ->limit($count)
                ->get(['id', 'name', 'fixed_price', 'size']);
    
            return response()->json($randomLands);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to get random lands: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get random lands'], 500);
        }
    }


    public function create(Request $request)
    {
        try {
            $validatedData = $this->validateScratchBoxData($request);
            $lands = $this->getValidLandsForScratchBox($validatedData['land_ids']);

            DB::beginTransaction();

            $scratchBox = $this->createScratchBox($validatedData, $lands);
            $this->attachLandsToScratchBox($scratchBox, $lands);

            DB::commit();

            return response()->json(['message' => 'Scratch box created successfully', 'scratch_box' => $scratchBox]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create scratch box: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create scratch box'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $scratchBox = ScratchBox::findOrFail($id);

            DB::transaction(function () use ($scratchBox) {
                $scratchBox->lands()->update(['is_in_scratch' => false]);
                $scratchBox->delete();
            });

            return response()->json(['message' => 'Scratch box deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete scratch box: ' . $e->getMessage(), ['id' => $id]);
            return response()->json(['error' => 'Failed to delete scratch box'], 500);
        }
    }

    public function getAvailableLands()
    {
        try {
            $lands = Land::where('is_locked', true)
                ->where('is_in_scratch', false)
                ->paginate(10);
            return response()->json($lands);
        } catch (\Exception $e) {
            Log::error('Failed to fetch available lands: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch available lands'], 500);
        }
    }

    public function getAllAvailableLandIds()
    {
        try {
            $landIds = Land::where('is_locked', true)
                ->where('is_in_scratch', false)
                ->pluck('id')
                ->toArray();
            return response()->json($landIds);
        } catch (\Exception $e) {
            Log::error('Failed to fetch available land IDs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch available land IDs'], 500);
        }
    }

    private function validateScratchBoxData(Request $request)
    {

        return $request->validate([
            'name' => 'required|string|max:255',
            'land_ids' => 'required|array',
            'land_ids.*' => 'exists:lands,id',
        ]);
    }

    private function getValidLandsForScratchBox(array $landIds)
    {
        $lands = Land::whereIn('id', $landIds)
            ->where('is_locked', true)
            ->where('is_in_scratch', false)
            ->get();

        if ($lands->count() != count($landIds)) {
             return response()->json(['error' => 'Invalid land IDs'], 400);
        }

        return $lands;
    }

    private function createScratchBox(array $validatedData, $lands)
    {
        return ScratchBox::create([
            'name' => $validatedData['name'],
            'price' => $lands->sum('fixed_price'),
        ]);
    }

    private function attachLandsToScratchBox(ScratchBox $scratchBox, $lands)
    {
        $scratchBox->lands()->attach($lands->pluck('id'));
        $lands->each->update(['is_in_scratch' => true]);
    }
}
