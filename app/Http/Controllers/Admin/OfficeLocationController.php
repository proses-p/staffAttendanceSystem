<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfficeLocation;
use Illuminate\Http\Request;

class OfficeLocationController extends Controller
{
    public function store(Request $request) {
        $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);
        $location = OfficeLocation::first();

        if ($location) {
            $location->update([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);
        } else {
            $location = OfficeLocation::create([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Office location saved successfully.',
        ]);
    }
}
