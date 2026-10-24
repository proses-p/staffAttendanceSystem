<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceEnrollmentController extends Controller
{
    public function status(Request $request): JsonResponse {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'enrolled' => !empty($user->face_id_hash),
            'enrolled_at' => $user->face_enrolled_at,
        ]);
    }

    public function enroll(Request $request): JsonResponse {
        $validated = $request->validate([
            'face_id' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $user = $request->user();

        if (!empty($user->face_id_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Face is already registered. Contact an administrator to reset it.',
            ], 409);
        }

        $user->face_id_hash = hash('sha256', $validated['face_id']);
        $user->face_enrolled_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Face enrollment record created',
            'enrolled' => true,
            'enrolled_at' => $user->face_enrolled_at,
        ], 201);
    }
}
