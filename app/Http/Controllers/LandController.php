<?php

namespace App\Http\Controllers;

use App\Models\Land;
use App\Models\User;
use Cache;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LandController extends Controller
{
    private $nftController;

    public function __construct(NftController $nftController)
    {
        $this->nftController = $nftController;
    }



    public function getBoundLands(Request $request): JsonResponse
    {
        $bounds = $request->input('bounds');
        $zoom = $request->input('zoom');

        if (!$zoom || $zoom < 4) {
            return response()->json([]);
        }

        $query = Land::query()
            ->select(
                'id',
                'type',
                DB::raw('ST_AsGeoJSON(geom) as geom'),
                'size',
                'owner_id',
                'is_locked',
                'building_id',
                DB::raw('ST_AsText(centroid) as centroid'),
                'fixed_price'
            );

        if ($bounds) {
            $boundingBox = "ST_MakeEnvelope({$bounds['west']}, {$bounds['south']}, {$bounds['east']}, {$bounds['north']}, 4326)";
            $query->whereRaw("ST_Intersects(geom, $boundingBox)");

            $limit = $this->getLimitByZoom($zoom);
            $query->limit($limit);
        }

        $lands = $query->get();

        // Process each land
        $lands = $lands->map(function ($land) {
            $geom = json_decode($land->geom, true);
            return [
                'id' => $land->id,
                'type' => $land->type,
                'coordinates' => json_encode($this->parseCoordinates($geom)),
                'size' => $land->size,
                'is_locked' => $land->is_locked,
                'owner_id' => $land->owner_id,
                'center_lat' => $land->center_lat,
                'center_long' => $land->center_long,
                'building_id' => $land->building_id,
                'fixed_price' => $land->fixed_price,
                'is_for_sale' => $land->is_for_sale,
                'has_active_auction' => $land->has_active_auction
            ];
        });

        return response()->json($lands);
    }
    private function parseCoordinates($geom)
    {
        if (!is_array($geom) || !isset($geom['type'])) {
            return null;
        }

        switch ($geom['type']) {
            case 'MultiPolygon':
                return $geom['coordinates'];
            case 'Polygon':
                return [$geom['coordinates']];
            default:
                return null;
        }
    }
    private function getLimitByZoom(float $zoom): int
    {
        if ($zoom < 8) {
            return 400;
        } elseif ($zoom < 12) {
            return 1500;
        } elseif ($zoom < 15) {
            return 3000;
        } else {
            return 4000;
        }
    }

    public function getUserLands()
    {
        $user = Auth::user();
        $lands = Land::where('owner_id', $user->id)->get();
        return response()->json($lands);
    }
    public function all(): JsonResponse
    {
        $activeLands = Land::get();
        return response()->json($activeLands);
    }


    public function show($id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $land = Land::select(
                'id',
                'building_id',
                'building_name',
                'type',
                'is_locked',
                'size',
                'owner_id',
                'fixed_price',
                'transfer_times',
                DB::raw('ST_AsText(centroid) as centroid')
            )
                ->with('owner:id,nickname,address')
                ->lockForUpdate()
                ->findOrFail($id);
            if (connection_aborted()) {
                abort(499, 'Client Closed Request');
            }
            $this->syncOwnerWithBlockchain($land);
            $land->refresh();

            $response = $land->toArray();
            $response['has_active_auction'] = $land->has_active_auction;
            $response['minimum_bid'] = $land->minimum_bid;
            $response['center_lat'] = $land->center_lat;
            $response['center_long'] = $land->center_long;
            $response['transfer_times'] = $land->transfer_times;
            $response['updated_at'] = $land->updated_at;

            $response['owner'] = [
                'id' => $land->owner->id,
                'nickname' => $land->owner->nickname,
                'address' => $land->owner->address
            ];

            $response['owner_id'] = $land->owner_id;
            $response['owner_nickname'] = $land->owner->nickname;

            if ($land->activeAuction) {
                $response['active_auction'] = $land->formatted_active_auction;
            }

            return response()->json($response);
        });
    }
    private function syncOwnerWithBlockchain(Land $land)
    {
        $tokenId = $land->id;
        $blockchainOwnerAddress = $this->nftController->getTokenOwner($tokenId);

        if ($blockchainOwnerAddress) {
            // Convert the blockchain address to lowercase for comparison
            $blockchainOwnerAddress = strtolower($blockchainOwnerAddress);

            // Get the current owner from the database
            $currentOwner = $land->owner;

            // Check if the blockchain owner address matches the current owner's address (case-insensitive)
            if ($currentOwner && strtolower($currentOwner->address) !== $blockchainOwnerAddress) {
                // The owner has changed, find the new owner in the database (case-insensitive)
                $newOwner = User::whereRaw('LOWER(address) = ?', [$blockchainOwnerAddress])->first();

                if ($newOwner) {
                    // Update the land's owner
                    $land->owner_id = $newOwner->id;

                    if ($land->transfer_times == 0) {
                        $land->transfer_times = 1;
                    }

                    $land->save();

                    // Reload the owner relationship
                    $land->load('owner');
                }
            } else if ($currentOwner && $land->transfer_times == 0) {
                $land->transfer_times = 2;
                $land->save();
            }
        }
    }
}
