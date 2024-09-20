<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('nickname') && !empty($request->nickname)) {
            $query->where('nickname', 'like', '%' . $request->nickname . '%');
        }

        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->has('city_id') && !empty($request->city_id)) {
            $query->where('city_id', $request->city_id);
        }

        $perPage = $request->input('per_page', 10);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    public function show(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => $user
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $validatedData = $request->validate([
            'current_mission' => 'sometimes|min:0|max:100',
            'avatar_url' => 'sometimes|string|max:255',
            'coordinates' => 'sometimes|json',
            'nickname' => 'sometimes|string|min:3|max:80',
            'city_id' => 'sometimes|min:0',
        ]);

        try {
            $user->update($validatedData);
            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            Log::error('User update failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return response()->json(['error' => 'User update failed: ' . $e->getMessage()], 500);
        }
    }

    public function getReferralTree(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'referral_tree' => $user->referralTree,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validatedData = $request->validate([
            'nickname' => 'required|string|min:3|max:80',
            'referral_code' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $referralApplied = false;
            $updatedFields = [];
            $errorMessages = [];

            if ($validatedData['referral_code']) {
                $invitor = User::where('referral_code', $validatedData['referral_code'])->first();

                if (!$invitor) {
                    $errorMessages[] = 'Invalid referral code.';
                } elseif ($invitor->id === $user->id) {
                    $errorMessages[] = 'You cannot use your own referral code.';
                } elseif ($user->inviter_id) {
                    $errorMessages[] = 'You have already applied a referral code.';
                } else {
                    $invitorCpAsset = Asset::firstOrCreate(
                        ['user_id' => $invitor->id, 'type' => 'cp'],
                        ['amount' => 0]
                    );
                    $invitorCpAsset->increment('amount', 1000);

                    $invitedUserCpAsset = Asset::firstOrCreate(
                        ['user_id' => $user->id, 'type' => 'cp'],
                        ['amount' => 0]
                    );
                    $invitedUserCpAsset->increment('amount', 500);

                    $user->inviter_id = $invitor->id;
                    $referralApplied = true;
                    $updatedFields[] = 'inviter_id';
                }
            }

            if ($user->nickname !== $validatedData['nickname']) {
                $user->nickname = $validatedData['nickname'];
                $updatedFields[] = 'nickname';
            }

            if (!empty($errorMessages)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => implode(' ', $errorMessages),
                    'updatedFields' => [],
                    'referralApplied' => false,
                ], 400);
            }

            $user->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully' . ($referralApplied ? ' Referral code applied.' : ''),
                'updatedFields' => $updatedFields,
                'referralApplied' => $referralApplied,
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Profile update failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred. Please try again.',
                'updatedFields' => [],
                'referralApplied' => false,
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
