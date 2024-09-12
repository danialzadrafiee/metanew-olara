<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index()
    {
        return City::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:cities',
            'avatar_spawn_coordinates' => 'required|string',
            'car_spawn_coordinates' => 'required|string',
            'chestman_spawn_coordinates' => 'required|string',
            'fob_spawn_coordinates' => 'required|string',
            'marker_spawn_coordinates' => 'required|string',
            'gems_spawn_coordinates' => 'required|file',
        ]);

        $validated = $this->convertCoordinates($validated);

        $geojson = json_decode(file_get_contents($request->file('gems_spawn_coordinates')), true);
        $gems_coordinates = $this->extractCoordinatesFromGeoJSON($geojson);

        $validated['gems_spawn_coordinates'] = $gems_coordinates;

        return City::create($validated);
    }


    public function show($id)
    {
        return City::find($id);
    }

    public function update(Request $request, $id)
    {
        $city =  City::find($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:cities,name,' . $city->id,
            'avatar_spawn_coordinates' => 'sometimes|string',
            'car_spawn_coordinates' => 'sometimes|string',
            'chestman_spawn_coordinates' => 'sometimes|string',
            'fob_spawn_coordinates' => 'sometimes|string',
            'marker_spawn_coordinates' => 'sometimes|string',
            'gems_spawn_coordinates' => 'sometimes|file',
        ]);

        $validated = $this->convertCoordinates($validated);

        if ($request->hasFile('gems_spawn_coordinates')) {
            $geojson = json_decode(file_get_contents($request->file('gems_spawn_coordinates')), true);
            $gems_coordinates = $this->extractCoordinatesFromGeoJSON($geojson);
            $validated['gems_spawn_coordinates'] = $gems_coordinates;
        }

        $city->update($validated);
        return $city;
    }

    private function extractCoordinatesFromGeoJSON($geojson)
    {
        $coordinates = [];
        foreach ($geojson['features'] as $feature) {
            $coordinates[] = $feature['geometry']['coordinates'];
        }
        return $coordinates;
    }
    private function convertCoordinates($data)
    {
        $coordinateFields = [
            'avatar_spawn_coordinates',
            'car_spawn_coordinates',
            'chestman_spawn_coordinates',
            'fob_spawn_coordinates',
            'marker_spawn_coordinates',
        ];

        foreach ($coordinateFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = array_map('floatval', explode(',', $data[$field]));
            }
        }

        return $data;
    }

    public function destroy($id)
    {
        City::find($id)->delete();
        return response()->json(['message' => 'City deleted successfully']);
    }
}
