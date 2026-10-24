<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffInvitationMail;
use App\Models\Company;
use App\Models\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffInvitationController extends Controller
{
    public function register(Request $request, string $token)
    {
        $invitation = StaffInvitation::where('token', $token)->first();

        abort_if(! $invitation, 404, 'This invitation is invalid.');
        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired.');
        abort_if($invitation->used_at, 410, 'This invitation has already been used.');
        abort_if(
            ! $invitation->company ||
                $invitation->company->status !== Company::STATUS_ACTIVE,
            410,
            'This invitation is not assigned to an active company. Contact the inviter for a new invitation.'
        );

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'organization' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'confirmed'],
            ]);

            DB::transaction(function () use ($data, $invitation) {
                $lockedInvitation = StaffInvitation::whereKey($invitation->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_if($lockedInvitation->used_at, 410, 'This invitation has already been used.');
                abort_if(
                    $lockedInvitation->expires_at->isPast() ||
                        ! $lockedInvitation->company ||
                        $lockedInvitation->company->status !== Company::STATUS_ACTIVE,
                    410,
                    'This invitation is no longer valid. Contact the inviter for a new invitation.'
                );
                abort_unless(
                    in_array($lockedInvitation->role, ['staff', 'admin'], true),
                    410,
                    'This invitation role is invalid. Contact the inviter for a new invitation.'
                );

                $user = $lockedInvitation->company->users()->make([
                    'name' => $data['name'],
                    'email' => $lockedInvitation->email,
                    'organization' => $data['organization'],
                    'password' => Hash::make($data['password']),
                ]);
                $user->role = $lockedInvitation->role;
                $user->is_active = true;
                $user->save();

                $lockedInvitation->update(['used_at' => now()]);
            });

            return redirect()->route('login')->with('status', 'Registration complete. Please log in.');
        }

        return view('auth.staff-register', compact('invitation'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'role' => ['sometimes', Rule::in(['staff', 'admin'])],
        ]);

        $invitation = $request->user()->company->staffInvitations()->create([
            'email' => $data['email'],
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);
        $invitation->role = $data['role'] ?? 'staff';
        $invitation->save();

        Mail::to($invitation->email)->send(new StaffInvitationMail($invitation));

        return response()->json([
            'success' => true,
            'message' => 'Invitation created successfully.',
        ]);
    }
}
