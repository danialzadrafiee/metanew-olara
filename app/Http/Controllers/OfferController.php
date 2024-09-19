<?php

namespace App\Http\Controllers;

use App\Models\Land;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfferController extends Controller
{
    public function getOffersByLand($landId): JsonResponse
    {
        $user = Auth::user();
        $land = Land::findOrFail($landId);
        $offers = Offer::where('land_id', $landId)
            ->with('user:id,nickname')
            ->select('id', 'user_id', 'price', 'created_at')
            ->orderBy('price', 'desc')
            ->get();
        $highest_offer = $offers->first();
        $user_offer = $offers->firstWhere('user_id', $user->id);

        $formattedOffers = $offers->map(function ($offer) {
            return [
                $offer
            ];
        });

        return response()->json([
            'offers' => $formattedOffers,
            'highest_offer' => $highest_offer ? $highest_offer->price : null,
            'user_offer' => $user_offer ? [
                'id' => $user_offer->id,
                'offer' => $user_offer->price,
                'date' => $user_offer->created_at->timestamp * 1000,
            ] : null,
            'land_owner' => $land->owner_id,
        ]);
    }

    public function submitOffer(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'land_id' => 'required|exists:lands,id',
            'price' => 'required|numeric|min:0',
        ]);
        $user = User::where('id', Auth::user()->id)->first();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        try {
            DB::beginTransaction();
            $offer = new Offer([
                'land_id' => $validatedData['land_id'],
                'user_id' => $user->id,
                'price' => $validatedData['price'],
            ]);
            $offer->save();
            DB::commit();
            return response()->json([
                'message' => 'Offer submitted successfully',
                'offer' => [
                    'id' => $offer->id,
                    'offer' => $offer->price,
                    'user' => $user->nickname ?? 'Unknown',
                    'date' => $offer->created_at->timestamp * 1000,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit offer: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function deleteOffer(Request $request, $offerId): JsonResponse
    {
        $user = User::where('id', Auth::user()->id)->first();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $offer = Offer::findOrFail($offerId);

        if ($offer->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $offer->delete();

            DB::commit();

            return response()->json(['message' => 'Offer deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete offer: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete offer'], 500);
        }
    }

    public function updateOffer(Request $request, $offerId): JsonResponse
    {
        $validatedData = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $user = User::where('id', Auth::user()->id)->first();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $offer = Offer::findOrFail($offerId);

        if ($offer->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $offer->price = $validatedData['price'];
            $offer->save();

            DB::commit();

            return response()->json([
                'message' => 'Offer updated successfully',
                'offer' => [
                    'id' => $offer->id,
                    'offer' => $offer->price,
                    'user' => $user->nickname ?? 'Unknown',
                    'date' => $offer->updated_at->timestamp * 1000,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update offer: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function getOffersByUser()
    {
        $user = Auth::user();
        $offers = Offer::where('user_id', $user->id)->get();
        return response()->json($offers);
    }

    public function acceptOffer($offerId): JsonResponse
    {
        try {
            $offer = Offer::findOrFail($offerId);
            $updatedLand = $offer->accept();

            return response()->json([
                'message' => 'Offer accepted successfully.',
                'land' => $updatedLand,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
