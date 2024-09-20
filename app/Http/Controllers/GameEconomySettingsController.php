<?php

namespace App\Http\Controllers;

use App\Models\GameEconomySettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GameEconomySettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = GameEconomySettings::all();
        return response()->json($settings);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'value' => 'required|numeric',
        ]);

        $setting = GameEconomySettings::findOrFail($id);
        $setting->update(['value' => $request->value]);

        return response()->json([
            'message' => 'Setting updated successfully',
            'setting' => $setting
        ]);
    }
}