<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\StaffInvitationMail;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'companies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'status']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:companies,slug'],
        ]);

        $company = Company::create($data);
        $company->refresh();

        return response()->json([
            'success' => true,
            'company' => $company->only(['id', 'name', 'slug', 'status']),
        ], 201);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
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

        return response()->json([
            'success' => true,
            'company' => $company->only(['id', 'name', 'slug', 'status']),
        ]);
    }

    public function inviteAdmin(Request $request, Company $company): JsonResponse
    {
        if ($company->status !== Company::STATUS_ACTIVE) {
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

        return response()->json([
            'success' => true,
            'message' => 'Company administrator invitation created successfully.',
        ], 201);
    }
}
