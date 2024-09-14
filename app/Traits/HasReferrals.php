<?php
// app/Traits/HasReferrals.php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasReferrals
{
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'inviter_id');
    }

    public function allReferrals(): HasMany
    {
        return $this->referrals()->with('allReferrals');
    }

    public function getReferralTreeAttribute()
    {
        return $this->allReferrals->map(function ($referral) {
            return [
                'id' => $referral->id,
                'nickname' => $referral->nickname,
                'referrals' => $referral->referralTree,
            ];
        });
    }

    public function applyReferral(string $referralCode): bool
    {
        if ($this->inviter_id) {
            return false;
        }

        $referrer = User::where('referral_code', $referralCode)->first();
        if (!$referrer || $referrer->id === $this->id) {
            return false;
        }

        $this->inviter_id = $referrer->id;
        return $this->save();
    }
}
