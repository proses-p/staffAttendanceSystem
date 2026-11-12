<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffNotification;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    public function fetchUnread(Request $request)
    {
        $user = $request->user();

        // Chukua ambazo hazijafika kwenye simu ya staff bado
        $notifications = StaffNotification::where('user_id', $user->id)
            ->where('is_pushed', false)
            ->orderBy('created_at', 'asc')
            ->get();

        // Ziweke alama kuwa zishachukuliwa na simu ili zisijirudie tena
        if ($notifications->isNotEmpty()) {
            StaffNotification::whereIn('id', $notifications->pluck('id'))
                ->update(['is_pushed' => true]);
        }

        return response()->json([
            'status' => 'success',
            'notifications' => $notifications,
        ]);
    }
}
