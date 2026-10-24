<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfficeLocation;
use Illuminate\Http\Request;

class OfficeLocationController extends Controller
{
    public function store(Request $request) {
        $companyId = $request->user()->company_id;

        if ($request->has('allowed_radius')) {
            $data = $request->validate([
                'allowed_radius' => ['required', 'integer', 'min:1'],
            ]);
            $location = OfficeLocation::where('company_id', $companyId)->first();

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

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $location = OfficeLocation::where('company_id', $companyId)->first();

        if ($location) {
            $location->update([
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
            ]);
        } else {
            $location = new OfficeLocation([
                'office_name' => 'Main Office',
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
            ]);
            $location->company_id = $companyId;
            $location->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Office location saved successfully.',
        ]);
    }
}
