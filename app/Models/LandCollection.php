<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class LandCollection extends Model
{
    use HasFactory;

    protected $appends = ['contain_sold_land'];
    protected $fillable = ['is_active', 'is_locked', 'type'];

    public function lands(): HasMany
    {
        return $this->hasMany(Land::class);
    }

    public function backup(): HasOne
    {
        return $this->hasOne(LandCollectionBackup::class);
    }

    public function getContainSoldLandAttribute(): bool
    {
        return $this->lands()->where('owner_id', '!=', 1)->exists();
    }

    public function lockLands(): bool
    {
        DB::beginTransaction();
        try {
            $this->lands()->update(['is_locked' => true]);
            $this->update(['is_locked' => true]);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function unlockLands(): bool
    {
        DB::beginTransaction();
        try {
            if ($this->hasLandsInScratchBox()) {
                DB::rollBack();
                return false;
            }
            $this->lands()->update(['is_locked' => false]);
            $this->update(['is_locked' => false]);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function hasLandsInScratchBox(): bool
    {
        return $this->lands()
            ->whereHas('scratchBoxes', function ($query) {
                $query->where('status', '!=', 'opened');
            })
            ->exists();
    }

    public function toggleActive(): bool
    {
        DB::beginTransaction();
        try {
            $newActiveStatus = !$this->is_active;

            if ($newActiveStatus) {
                $this->activateCollection();
            } else {
                $this->deactivateCollection();
            }

            $this->update(['is_active' => $newActiveStatus]);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    private function activateCollection(): void
    {
        InactiveLand::where('land_collection_id', $this->id)
            ->chunkById(1000, function ($lands) {
                foreach ($lands as $land) {
                    Land::create($land->getAttributes());
                    $land->delete();
                }
            });
    }

    private function deactivateCollection(): void
    {
        $this->lands()
            ->chunkById(1000, function ($lands) {
                foreach ($lands as $land) {
                    InactiveLand::create($land->getAttributes());
                    $land->delete();
                }
            });
    }

    public function updateLandType(string $type): bool
    {
        DB::beginTransaction();
        try {
            $this->update(['type' => $type]);
            $this->lands()->update(['type' => $type]);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }
}