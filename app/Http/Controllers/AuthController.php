<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecoverAddressRequest;
use App\Models\User;
use Illuminate\Http\Request;
use SWeb3\Accounts;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
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

    private function authenticateWithToken($token, $address)
    {
        $tokenModel = PersonalAccessToken::findToken($token);
        if (!$tokenModel) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $tokenModel->tokenable;
        if (strcasecmp($user->address, $address) !== 0) {
            return response()->json(['error' => 'Token does not match the provided address', 'provider_address' => $address], 401);
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
            $user = User::where('address', $request->address)->first();
            if (!$user) {
                $user = User::create(['address' => $request->address]);
            }
            return $this->login($user);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    private function login(User $user)
    {
        try {
            $user->tokens()->delete();
            $newToken = $user->createToken('auth_token', ['*'], $this->getTokenExpiration());

            return $this->respondWithToken($newToken->plainTextToken, $user);
        } catch (\Exception $e) {
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
            $isVerified = strcasecmp($recoveredAddress, $request->address) === 0;


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
}