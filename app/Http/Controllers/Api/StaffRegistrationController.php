<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StaffRegistrationController extends Controller
{
    public function complete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'employee_id' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please check the information you entered',
                'error' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $user = DB::transaction(function () use ($data) {
            $invitation = StaffInvitation::where(
                'token',
                $data['token']
            )->lockForUpdate()->first();

            if (! $invitation) {
                throw ValidationException::withMessages([
                    'token' => ['This invitaion is invalid.'],
                ]);
            }

            if ($invitation->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => ['This invitaion has expired'],
                ]);
            }

            if (
                ! $invitation->company ||
                $invitation->company->status !== Company::STATUS_ACTIVE
            ) {
                throw ValidationException::withMessages([
                    'token' => ['This invitation is not assigned to an active company. Contact the inviter for a new invitation.'],
                ]);
            }

            if ($invitation->used_at !== null) {
                throw ValidationException::withMessages([
                    'token' => ['This invitation has already been used'],
                ]);
            }

            if (! in_array($invitation->role, ['staff', 'admin'], true)) {
                throw ValidationException::withMessages([
                    'token' => ['This invitation role is invalid. Contact the inviter for a new invitation.'],
                ]);
            }

            if (User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['This account has already exist for this invitation.'],
                ]);
            }

            $user = $invitation->company->users()->make([
                'name' => $data['name'],
                'email' => $invitation->email,
                'organization' => $data['organization'] ?? null,
                'phone' => $data['phone'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'password' => Hash::make($data['password']),
            ]);
            $user->role = $invitation->role;
            $user->is_active = true;
            $user->save();

            $invitation->update([
                'used_at' => now(),
            ]);

            return $user;

        });

        // json response
        return response()->json([
            'success' => true,
            'message' => 'Registration completed successfully. You can now sign in.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }
}
