<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid email or password',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return response()->json([
                'message' => 'Your account has been deactivated',
            ], 403);
        }

        if (
            $user->role !== 'super_admin' &&
            (! $user->company || $user->company->status !== Company::STATUS_ACTIVE)
        ) {
            Auth::logout();

            return response()->json([
                'message' => 'Your company is unavailable or has not been assigned. Contact your administrator.',
            ], 403);
        }

        $token = $user->createToken('staffflow-mobile')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    // logout function
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'You have successfull logged out!',
        ]);
    }
}
