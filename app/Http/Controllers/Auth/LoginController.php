<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

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
            return back()->withErrors([
                'email' => 'Invalid email or password',
            ])->onlyInput('email');
        }
        $request->session()->regenerate();
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account has been deactivated',
            ]);
        }

        if ($companyAccessMessage = $user->companyAccessMessage()) {
            Auth::logout();

            return back()->withErrors([
                'email' => $companyAccessMessage,
            ]);
        }

        if ($user->role === 'super_admin') {
            return redirect()->route('platform.dashboard');
        }
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        if ($user->role === 'manager') {
            return redirect()->route('admin.field-expenses.index');
        }

        return redirect()->route('staff.dashboard');
    }

    public function logout(Request $request)
    {
        // $user = Auth::user();
        // $currentAttendance = Attendance::where('user_id', $user->id)->orderByDesc('updated_at')->first();

        // $data = [
        //     "Current User"=>$user,
        //     "Attend Record"=>$currentAttendance
        // ];

        // dd($data);

        // $currentAttendance->check_out_time = Carbon::now();
        // $currentAttendance->save();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
