<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function sendCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $validated['email']],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        Mail::to($validated['email'])->send(new PasswordResetCodeMail($code));

        return response()->json([
            'message' => 'A password reset code has been sent to your email address.',
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated): void {
            $resetToken = DB::table('password_reset_tokens')
                ->where('email', $validated['email'])
                ->lockForUpdate()
                ->first();

            if (
                ! $resetToken ||
                ! $resetToken->created_at ||
                Carbon::parse($resetToken->created_at)->addMinutes(15)->isPast() ||
                ! Hash::check($validated['code'], $resetToken->token)
            ) {
                throw ValidationException::withMessages([
                    'code' => ['The reset code is invalid or has expired.'],
                ]);
            }

            $user = User::query()
                ->where('email', $validated['email'])
                ->lockForUpdate()
                ->firstOrFail();
            $user->password = $validated['password'];
            $user->save();
            $user->tokens()->delete();

            DB::table('password_reset_tokens')
                ->where('email', $validated['email'])
                ->delete();
        });

        return response()->json([
            'message' => 'Your password has been reset successfully.',
        ]);
    }
}
