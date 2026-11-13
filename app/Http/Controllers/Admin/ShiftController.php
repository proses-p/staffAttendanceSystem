<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftController extends Controller
{
    private const WEEKDAYS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        return view('admin.shifts.index', [
            'shifts' => Shift::query()
                ->where('company_id', $companyId)
                ->withCount('users')
                ->orderBy('name')
                ->get(),
            'staff' => User::query()
                ->where('company_id', $companyId)
                ->where('role', 'staff')
                ->with('shift')
                ->orderBy('name')
                ->get(),
            'weekdays' => self::WEEKDAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedShift($request);
        $shift = new Shift($data);
        $shift->company_id = $request->user()->company_id;
        $shift->save();

        return redirect()->route('admin.shifts.index')->with('success', 'Shift created.');
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $this->ensureCompanyShift($request, $shift);
        $shift->update($this->validatedShift($request));

        return redirect()->route('admin.shifts.index')->with('success', 'Shift updated.');
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        $this->ensureCompanyShift($request, $shift);
        $shift->delete();

        return redirect()->route('admin.shifts.index')->with('success', 'Shift deleted.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('company_id', $companyId)
                    ->where('role', 'staff'),
            ],
            'shift_id' => [
                'nullable',
                Rule::exists('shifts', 'id')->where('company_id', $companyId),
            ],
        ]);

        User::query()
            ->where('company_id', $companyId)
            ->where('role', 'staff')
            ->whereKey($data['user_id'])
            ->update(['shift_id' => $data['shift_id'] ?? null]);

        return redirect()->route('admin.shifts.index')->with('success', 'Staff shift assignment updated.');
    }

    private function validatedShift(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_period_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'string', 'distinct', Rule::in(array_keys(self::WEEKDAYS))],
        ]);
    }

    private function ensureCompanyShift(Request $request, Shift $shift): void
    {
        abort_unless((int) $shift->company_id === (int) $request->user()->company_id, 404);
    }
}
