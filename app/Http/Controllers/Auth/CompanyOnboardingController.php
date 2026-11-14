<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanyOnboardingController extends Controller
{
    public function register(Request $request, string $token)
    {
        $invitation = $this->validInvitation($token);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'company_name' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user = DB::transaction(function () use ($data, $invitation, $token) {
                $lockedInvitation = StaffInvitation::query()
                    ->whereKey($invitation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if(
                    $lockedInvitation->role !== 'admin' ||
                        $lockedInvitation->token !== $token,
                    404,
                    'This company administrator invitation is invalid.'
                );
                abort_if($lockedInvitation->used_at !== null, 410, 'This invitation has already been used.');
                abort_if(
                    $lockedInvitation->expires_at->isPast(),
                    410,
                    'This invitation has expired.'
                );

                $company = Company::query()
                    ->whereKey($lockedInvitation->company_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $company->name = $data['company_name'];
                $company->status = Company::STATUS_ACTIVE;
                $company->save();

                $company->attendanceSetting()->firstOrCreate([], [
                    'expected_work_minutes' => 480,
                    'grace_period_minutes' => 0,
                    'working_days' => [1, 2, 3, 4, 5],
                ]);

                $user = $company->users()->make([
                    'name' => $data['name'],
                    'email' => $lockedInvitation->email,
                    'organization' => $company->name,
                    'phone' => $data['phone'] ?? null,
                    'password' => Hash::make($data['password']),
                ]);
                $user->role = 'admin';
                $user->is_active = true;
                $user->save();

                $lockedInvitation->update(['used_at' => now()]);

                return $user;
            });

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('admin.dashboard');
        }

        return view('auth.company-onboarding', compact('invitation'));
    }

    private function validInvitation(string $token): StaffInvitation
    {
        $invitation = StaffInvitation::query()
            ->where('token', $token)
            ->where('role', 'admin')
            ->first();

        abort_if($invitation === null, 404, 'This company administrator invitation is invalid.');
        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired.');
        abort_if($invitation->used_at !== null, 410, 'This invitation has already been used.');

        return $invitation;
    }
}
