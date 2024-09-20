<?php
// app/Traits/HasAssets.php

namespace App\Traits;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Log;

trait HasAssets
{
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function getAssetAttribute($type)
    {
        $asset = $this->assets->firstWhere('type', $type);
        return [
            'free' => $asset ? $asset->amount - $asset->locked_amount : 0,
            'locked' => $asset ? $asset->locked_amount : 0,
            'total' => $asset ? $asset->amount : 0,
        ];
    }

    public function lockAsset(string $type, int $amount): bool
    {

        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->where('type', $type)->lockForUpdate()->first();
            if (!$asset) {
                $asset = $this->assets()->create(['type' => $type, 'amount' => 0, 'locked_amount' => 0]);
            }
            if ($asset->amount - $asset->locked_amount < $amount) {
                return false;
            }
            $asset->locked_amount += $amount;
            $result = $asset->save();
            return $result;
        });
    }

    public function unlockAsset(string $type, int $amount): bool
    {
        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->where('type', $type)->lockForUpdate()->first();

            if (!$asset || $asset->locked_amount < $amount) {
                return false;
            }

            $asset->locked_amount -= $amount;
            return $asset->save();
        });
    }

    public function addAsset(string $type, float $amount): bool
    {

        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->firstOrCreate(
                ['type' => $type],
                ['amount' => 0, 'locked_amount' => 0]
            );
            $oldAmount = $asset->amount;
            $asset->amount += $amount;
            $result = $asset->save();

            return $result;
        });
    }

    public function removeAsset(string $type, float $amount): bool
    {

        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->where('type', $type)->lockForUpdate()->first();

            if (!$asset) {
                return false;
            }

            $availableAmount = $asset->amount - $asset->locked_amount;

            if ($availableAmount < $amount) {
                return false;
            }

            $oldAmount = $asset->amount;
            $asset->amount -= $amount;
            $result = $asset->save();


            return $result;
        });
    }

    public function removeLockedAsset(string $type, int $amount): bool
    {
        return DB::transaction(function () use ($type, $amount) {
            $asset = $this->assets()->where('type', $type)->lockForUpdate()->first();

            if (!$asset || $asset->locked_amount < $amount) {
                return false;
            }

            $asset->locked_amount -= $amount;
            $asset->amount -= $amount;

            return $asset->save();
        });
    }

    public function hasSufficientAsset(string $type, int $amount): bool
    {
        $asset = $this->assets->firstWhere('type', $type);
        return $asset && ($asset->amount - $asset->locked_amount) >= $amount;
    }

    public function getAssetsDataAttribute()
    {
        return $this->assets->keyBy('type')->map(function ($asset) {
            return [
                'total' => $asset->amount,
                'free' => $asset->amount - $asset->locked_amount,
                'locked' => $asset->locked_amount
            ];
        });
    }

    public function updateAsset(string $assetType, int $amount): bool
    {
        $asset = $this->assets->firstWhere('type', $assetType);
        if (!$asset) {
            return false;
        }
        $asset->amount = max(0, $asset->amount + $amount);
        return $asset->save();
    }

    public function setAssetExact(string $assetType, int $amount): bool
    {
        $asset = $this->assets->firstWhere('type', $assetType);
        if (!$asset) {
            return false;
        }
        $asset->amount = max(0, $amount);
        return $asset->save();
    }

    public function transferAsset(User $recipient, string $type, int $amount): bool
    {
        return DB::transaction(function () use ($recipient, $type, $amount) {
            if (!$this->removeAsset($type, $amount)) {
                return false;
            }
            return $recipient->addAsset($type, $amount);
        });
    }
}
