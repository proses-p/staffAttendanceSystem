<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfficeLocation;
use Illuminate\Http\Request;

class OfficeLocationController extends Controller
{
    public function store(Request $request) {
        if ($request->has('allowed_radius')) {
            $data = $request->validate([
                'allowed_radius' => ['required', 'integer', 'min:1'],
            ]);
            $location = OfficeLocation::first();

            if (!$location) {
                return redirect()
                    ->route('admin.dashboard')
                    ->with('error', 'Save an office location before updating the allowed distance.');
            }

            $location->update([
                'allowed_radius' => $data['allowed_radius'],
            ]);

            return redirect()
                ->route('admin.dashboard')
                ->with('success', 'Allowed distance updated successfully.');
        }

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
