<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffInvitationMail;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StaffInvitationController extends Controller
{
    public function register(Request $request, string $token)
    {
        $invitation = StaffInvitation::where('token', $token)->first();

        abort_if(!$invitation, 404, 'This invitation is invalid.');
        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired.');
        abort_if($invitation->used_at, 410, 'This invitation has already been used.');

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'organization' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'confirmed'],
            ]);

            DB::transaction(function () use ($data, $invitation) {
                User::create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'organization' => $data['organization'],
                    'password' => Hash::make($data['password']),
                    'role' => 'staff',
                ]);

                $invitation->update(['used_at' => now()]);
            });

            return redirect()->route('login')->with('status', 'Registration complete. Please log in.');
        }

        return view('auth.staff-register', compact('invitation'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
            ],

        ]);

        $invitation = StaffInvitation::create([
            'email' => $data['email'],
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);

        Mail::to($invitation->email)->send(new StaffInvitationMail($invitation));

        return response()->json([
            'success' => true,
            'message' => 'Invitation created successfully.'
        ]);
    }
}
