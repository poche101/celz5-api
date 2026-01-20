<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Geocoder\Laravel\Facades\Geocoder;


class ChurchController extends Controller
{

public function locateBySearch(Request $request)
{
    $query = $request->input('search_query'); // e.g., "Admiralty Way, Lekki"

    // 1. Convert Street Name to Coordinates
    $result = Geocoder::geocode($query . ', Lagos, Nigeria')->get()->first();

    if (!$result) {
        return response()->json(['message' => 'Area not found'], 404);
    }

    $lat = $result->getCoordinates()->getLatitude();
    $lng = $result->getCoordinates()->getLongitude();

    // 2. Run the Haversine formula to find the closest church
    $closestChurch = Church::selectRaw("*,
        (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance", [$lat, $lng, $lat])
        ->orderBy("distance", "asc")
        ->first(); // Get only the single closest church

    return response()->json([
        'searched_area' => $query,
        'coordinates' => ['lat' => $lat, 'lng' => $lng],
        'nearest_church' => $closestChurch
    ]);
}
}
