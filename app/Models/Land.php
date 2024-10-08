<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Land extends Model
{
    protected $appends = [
        'owner_nickname',
        'has_active_auction',
        'minimum_bid',
        'is_for_sale',
        'center_lat',
        'center_long',
        'coordinates'
    ];
    protected $with = [
        'landCollection'
    ];
    protected $casts = [
        'is_in_scratch' => 'boolean',
        'is_locked' => 'boolean',
        'is_suspend' => 'boolean',
        'is_owner_landlord' => 'boolean',
        'size' => 'double',
        'is_first_transfer' => 'boolean',
        'fixed_price' => 'double',
    ];

 
    public function coordinates(): Attribute
    {
        return Attribute::make(
            get: function () {
                $result = DB::selectOne("SELECT ST_AsGeoJSON(geom) as geojson FROM lands WHERE id = ?", [$this->id]);
                return $result ? json_decode($result->geojson, true) : null;
            },
            set: function ($value) {
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                $geoJson = json_encode($value);
                DB::statement("UPDATE lands SET geom = ST_Multi(ST_GeomFromGeoJSON(?)) WHERE id = ?", [$geoJson, $this->id]);
            }
        );
    }




    public function centerLat(): Attribute
    {
        return Attribute::make(
            get: function () {
                $result = DB::selectOne("SELECT ST_Y(ST_AsText(centroid)) as lat FROM lands WHERE id = ?", [$this->id]);
                return $result ? $result->lat : null;
            }
        );
    }

    public function centerLong(): Attribute
    {
        return Attribute::make(
            get: function () {
                $result = DB::selectOne("SELECT ST_X(ST_AsText(centroid)) as lon FROM lands WHERE id = ?", [$this->id]);
                return $result ? $result->lon : null;
            }
        );
    }

    public function getIsForSaleAttribute()
    {
        if ($this->fixed_price > 0) {
            return true;
        }
        return false;
    }
    public function getOwnerNicknameAttribute()
    {
        if (!$this->owner_id) {
            return null;
        }
        return $this->owner->nickname ?? null;
    }


    public function getMinimumBidAttribute()
    {
        $activeAuction = $this->activeAuction;
        if (!$activeAuction) {
            return null;
        }

        $highestBid = $activeAuction->highest_bid;
        return $highestBid ? max($highestBid * 1.05, $activeAuction->minimum_price) : $activeAuction->minimum_price;
    }

    public function getHasActiveAuctionAttribute(): bool
    {
        return $this->activeAuction()->exists();
    }

    public function getFormattedActiveAuctionAttribute()
    {
        $activeAuction = $this->activeAuction;

        if (!$activeAuction) {
            return null;
        }

        return [
            'id' => $activeAuction->id,
            'land_id' => $activeAuction->land_id,
            'minimum_price' => $activeAuction->minimum_price,
            'end_time' => $activeAuction->end_time,
            'is_active' => $activeAuction->is_active,
            'highest_bid' => $activeAuction->highest_bid,
            'minimum_bid' => $this->minimum_bid,
            'created_at' => $activeAuction->created_at,
            'updated_at' => $activeAuction->updated_at,
            'bids' => $activeAuction->bids->sortByDesc('created_at')->values()->map(function ($bid) {
                return [
                    'id' => $bid->id,
                    'user' => [
                        'id' => $bid->user->id,
                        'nickname' => $bid->user->nickname,
                    ],
                    'user_nickname' => $bid->user->nickname,
                    'amount' => $bid->amount,
                    'created_at' => $bid->created_at,
                ];
            }),
        ];
    }



    protected function isForSale(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->fixed_price !== null && $this->fixed_price > 0,
        );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class);
    }

    public function activeAuction(): HasOne
    {
        return $this->hasOne(Auction::class)->active()->latest();
    }

    public function scratchBoxes()
    {
        return $this->belongsToMany(ScratchBox::class, 'scratch_box_land');
    }
    public function landCollection(): BelongsTo
    {
        return $this->belongsTo(LandCollection::class);
    }


    public function transfer($receiver_id)
    {
        $this->update([
            'owner_id' => $receiver_id,
            'fixed_price' => 0,
        ]);
    }

    public function transfers()
    {
        return $this->hasMany(LandTransfer::class);
    }
}
