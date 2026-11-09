<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\StaffInvitationMail;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function dashboard()
    {
        $hasAuditLog = Schema::hasTable('company_ownership_audits');

        return view('platform.dashboard', [
            'stats' => [
                'companies' => Company::query()->count(),
                'active_companies' => Company::query()->where('status', Company::STATUS_ACTIVE)->count(),
                'suspended_companies' => Company::query()->where('status', Company::STATUS_SUSPENDED)->count(),
                'staff' => User::query()->where('role', 'staff')->whereNotNull('company_id')->count(),
                'company_admins' => User::query()->where('role', 'admin')->whereNotNull('company_id')->count(),
                'attendance_today' => Attendance::query()
                    ->whereNotNull('company_id')
                    ->whereDate('attendance_date', today())
                    ->count(),
                'pending_admin_invitations' => StaffInvitation::query()
                    ->where('role', 'admin')
                    ->whereNull('used_at')
                    ->where('expires_at', '>', now())
                    ->whereNotNull('company_id')
                    ->count(),
            ],
            'recentCompanies' => Company::query()
                ->withCount([
                    'users as staff_count' => fn ($query) => $query->where('role', 'staff'),
                ])
                ->latest()
                ->limit(6)
                ->get(),
            'recentActivity' => $hasAuditLog
                ? DB::table('company_ownership_audits as audits')
                    ->join('companies', 'companies.id', '=', 'audits.company_id')
                    ->orderByDesc('audits.created_at')
                    ->limit(6)
                    ->get([
                        'audits.record_type',
                        'audits.record_id',
                        'audits.operator',
                        'audits.created_at',
                        'companies.name as company_name',
                    ])
                : collect(),
            'hasAuditLog' => $hasAuditLog,
        ]);
    }

    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'companies' => Company::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'status']),
            ]);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                Company::STATUS_ACTIVE,
                Company::STATUS_SUSPENDED,
            ])],
        ]);

        $companies = Company::query()
            ->withCount([
                'users as staff_count' => fn ($query) => $query->where('role', 'staff'),
                'users as admin_count' => fn ($query) => $query->where('role', 'admin'),
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('platform.companies.index', [
            'companies' => $companies,
            'filters' => $filters,
            'totalCompanies' => Company::query()->count(),
        ]);
    }

    public function show(Company $company)
    {
        $company->loadCount([
            'users as staff_count' => fn ($query) => $query->where('role', 'staff'),
            'users as admin_count' => fn ($query) => $query->where('role', 'admin'),
            'attendances',
        ]);

        $pendingAdminInvitations = $company->staffInvitations()
            ->where('role', 'admin')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->count();

        return view('platform.companies.show', compact('company', 'pendingAdminInvitations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:companies,slug'],
        ]);

        $company = Company::create($data);
        $company->refresh();

        if (! $request->expectsJson()) {
            return redirect()
                ->route('platform.companies.show', $company)
                ->with('success', 'Company created successfully.');
        }

        return response()->json([
            'success' => true,
            'company' => $company->only(['id', 'name', 'slug', 'status']),
        ], 201);
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('companies', 'slug')->ignore($company->id),
            ],
            'status' => ['sometimes', 'required', Rule::in([
                Company::STATUS_ACTIVE,
                Company::STATUS_SUSPENDED,
            ])],
        ]);

        $company->fill(collect($data)->except('status')->all());
        if (array_key_exists('status', $data)) {
            $company->status = $data['status'];
        }
        $company->save();

        if (! $request->expectsJson()) {
            return redirect()
                ->route('platform.companies.show', $company)
                ->with('success', 'Company details updated.');
        }

        return response()->json([
            'success' => true,
            'company' => $company->only(['id', 'name', 'slug', 'status']),
        ]);
    }

    public function inviteAdmin(Request $request, Company $company)
    {
        if ($company->status !== Company::STATUS_ACTIVE) {
            if (! $request->expectsJson()) {
                return back()->withErrors([
                    'email' => 'Company administrator invitations are available only for active companies.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Company administrator invitations are available only for active companies.',
            ], 422);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ]);

        $invitation = $company->staffInvitations()->create([
            'email' => $data['email'],
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);
        $invitation->role = 'admin';
        $invitation->save();

        Mail::to($invitation->email)->send(new StaffInvitationMail($invitation));

        if (! $request->expectsJson()) {
            return back()->with('success', 'Company administrator invitation sent.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Company administrator invitation created successfully.',
        ], 201);
    }
}
