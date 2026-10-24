<?php

namespace App\Http\Controllers;

use App\Models\OfficeLocation;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $staff = User::where('company_id', $request->user()->company_id)
            ->where('role', 'staff')
            ->latest()
            ->paginate(10);

        return view('admin.staff.index', compact('staff'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_id' => ['nullable', 'string', 'max:100', 'unique:users,employee_id'],
            'password' => ['required', 'confirmed'],
        ]);
        $staff = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);
        $staff->company_id = $request->user()->company_id;
        $staff->role = 'staff';
        $staff->save();

        return response()->json([
            'success' => true,
            'message' => 'Staff member registered successfull.',
            'staff' => $staff,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $staff)
    {
        abort_unless(
            $staff->role === 'staff' &&
                (int) $staff->company_id === (int) $request->user()->company_id,
            404
        );

        $attendance = $staff->attendances()
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in_time')
            ->first();
        $officeLocation = OfficeLocation::where('company_id', $request->user()->company_id)->first();

        return view('admin.staff.show', compact('staff', 'attendance', 'officeLocation'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $staff)
    {
        abort_unless(
            $staff->role === 'staff' &&
                (int) $staff->company_id === (int) $request->user()->company_id,
            404
        );

        return view('admin.staff.edit', compact('staff'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $staff)
    {
        abort_unless(
            $staff->role === 'staff' &&
                (int) $staff->company_id === (int) $request->user()->company_id,
            404
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'organization' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $staff->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'organization' => $data['organization'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $staff->update(['password' => Hash::make($data['password'])]);
        }

        return redirect()
            ->to(route('admin.dashboard').'#users')
            ->with('success', 'Staff member updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $staff)
    {
        abort_unless(
            $staff->role === 'staff' &&
                (int) $staff->company_id === (int) auth()->user()->company_id,
            404
        );

        try {
            DB::transaction(function () use ($staff) {
                StaffInvitation::where('company_id', auth()->user()->company_id)
                    ->where('email', $staff->email)
                    ->delete();
                $staff->delete();
            });
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->to(route('admin.dashboard').'#users')
                ->with('error', 'Unable to delete this staff member.');
        }

        return redirect()
            ->to(route('admin.dashboard').'#users')
            ->with('success', 'Staff member deleted successfully.');
    }
}
