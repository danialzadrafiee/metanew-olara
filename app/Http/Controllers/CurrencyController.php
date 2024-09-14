<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    public function getBalance($userId, $type)
    {
        $currency = Currency::where('user_id', $userId)
            ->where('type', $type)
            ->first();

        if (!$currency) {
            return response()->json(['message' => 'Currency not found'], 404);
        }

        return response()->json($currency);
    }

    public function lock(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:cp,meta',
            'amount' => 'required|numeric|min:0 ',
        ]);

        return DB::transaction(function () use ($request) {
            $currency = Currency::where('user_id', $request->user_id)
                ->where('type', $request->type)
                ->lockForUpdate()
                ->first();

            if (!$currency || $currency->getAvailableAmount() < $request->amount) {
                return response()->json(['message' => 'Insufficient funds'], 400);
            }

            $currency->locked_amount += $request->amount;
            $currency->save();

            return response()->json($currency);
        });
    }

    public function unlock(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:cp,meta,bnb',
            'amount' => 'required|min:0 ',
        ]);

        return DB::transaction(function () use ($request) {
            $currency = Currency::where('user_id', $request->user_id)
                ->where('type', $request->type)
                ->lockForUpdate()
                ->first();

            if (!$currency || $currency->locked_amount < $request->amount) {
                return response()->json(['message' => 'Invalid unlock amount'], 400);
            }

            $currency->locked_amount -= $request->amount;
            $currency->save();

            return response()->json($currency);
        });
    }

    public function add(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:cp,meta',
            'amount' => 'required|min:0 ',
        ]);

        return DB::transaction(function () use ($request) {
            $currency = Currency::firstOrNew([
                'user_id' => $request->user_id,
                'type' => $request->type,
            ]);

            $currency->amount += $request->amount;
            $currency->save();

            return response()->json($currency);
        });
    }

    public function subtract(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:cp,meta',
            'amount' => 'required|min:0 ',
        ]);

        return DB::transaction(function () use ($request) {
            $currency = Currency::where('user_id', $request->user_id)
                ->where('type', $request->type)
                ->lockForUpdate()
                ->first();

            if (!$currency || $currency->getAvailableAmount() < $request->amount) {
                return response()->json(['message' => 'Insufficient funds'], 400);
            }

            $currency->amount -= $request->amount;
            $currency->save();

            return response()->json($currency);
        });
    }
}
