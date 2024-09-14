<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecoverAddressRequest;
use App\Models\Asset;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Http\Request;
use SWeb3\Accounts;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class UserController extends Controller
{

    public function index(Request $request)
    {
        $query = User::query();

        // Filter by nickname
        if ($request->has('nickname') && !empty($request->nickname)) {
            $query->where('nickname', 'like', '%' . $request->nickname . '%');
        }

        // Filter by role
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Filter by city_id
        if ($request->has('city_id') && !empty($request->city_id)) {
            $query->where('city_id', $request->city_id);
        }

        // Paginate the results
        $perPage = $request->input('per_page', 10); // Default to 10 items per page
        $users = $query->paginate($perPage);

        return response()->json($users);
    }
    private function authenticateWithToken($token, $address)
    {
        $tokenModel = PersonalAccessToken::findToken($token);
        if (!$tokenModel) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $tokenModel->tokenable;
        if (strtolower($user->address) !== strtolower($address)) {
            return response()->json(['error' => 'Token does not match the provided address', 'provider_address' => strtolower($address)], 401);
        }

        if ($this->isTokenExpired($tokenModel)) {
            $tokenModel->delete();
            return response()->json(['error' => 'Token has expired'], 401);
        }

        return $this->respondWithToken($token, $user);
    }

    private function authenticateWithSignature(RecoverAddressRequest $request)
    {
        if (!$this->isSignatureVerified($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        try {
            $user = User::firstOrCreate(['address' => strtolower($request->address)]);
            return $this->login($user);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }


    private function login(User $user)
    {
        try {
            Log::info('Login attempt', ['user_id' => $user->id]);
            $user->tokens()->delete();
            $newToken = $user->createToken('auth_token', ['*'], $this->getTokenExpiration());
            Log::info('New token created', ['token_id' => $newToken->accessToken->id]);

            return $this->respondWithToken($newToken->plainTextToken, $user);
        } catch (\Exception $e) {
            Log::error('Token creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Token creation failed: ' . $e->getMessage()], 500);
        }
    }


    private function isTokenExpired($token)
    {
        if ($token->expires_at) {
            return $token->expires_at->isPast();
        }
        return $token->created_at->addYears(10)->isPast();
    }

    private function getTokenExpiration($token = null)
    {
        // if ($token && $token->expires_at) {
        //     return $token->expires_at;
        // }
        // // If no expiration is set, use a far future date
        return now()->addYears(10);
    }

    private function respondWithToken($token, $user)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $this->getTokenExpiration()->toDateTimeString(),
            'user' => $user
        ], 200);
    }

    private function isSignatureVerified(RecoverAddressRequest $request): bool
    {
        try {
            $recoveredAddress = Accounts::signedMessageToAddress($request->message, $request->signature);
            $isVerified = strtolower($recoveredAddress) == strtolower($request->address);

            Log::info('Signature verification attempt', [
                'message' => $request->message,
                'provided_address' => $request->address,
                'recovered_address' => $recoveredAddress,
                'is_verified' => $isVerified
            ]);

            return $isVerified;
        } catch (\Exception $e) {
            Log::error('Signature verification failed', [
                'message' => $request->message,
                'provided_address' => $request->address,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    public function authenticate(Request $request)
    {
        $token = $request->bearerToken();
        if ($token) {
            return $this->authenticateWithToken($token, $request->address);
        } else {
            $recoverAddressRequest = new RecoverAddressRequest();
            $recoverAddressRequest->merge($request->all());
            $validator = validator($recoverAddressRequest->all(), $recoverAddressRequest->rules());
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
            return $this->authenticateWithSignature($recoverAddressRequest);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Successfully logged out']);
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
            'current_mission' => 'sometimes|unsignedBigInteger|min:0|max:100',
            'avatar_url' => 'sometimes|string|max:255',
            'coordinates' => 'sometimes|json',
            'nickname' => 'sometimes|string|min:3|max:80',
            'city_id' => 'sometimes|unsignedBigInteger|min:0',
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

            // Check referral code if provided
            if ($validatedData['referral_code']) {
                $invitor = User::where('referral_code', $validatedData['referral_code'])->first();

                if (!$invitor) {
                    $errorMessages[] = 'Invalid referral code.';
                } elseif ($invitor->id === $user->id) {
                    $errorMessages[] = 'You cannot use your own referral code.';
                } elseif ($user->inviter_id) {
                    $errorMessages[] = 'You have already applied a referral code.';
                } else {
                    // Apply referral code
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

            // Update nickname
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
