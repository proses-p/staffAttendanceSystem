@extends('platform.layout')

@section('title', $company->name)

@section('content')
    <div class="platform-breadcrumbs">
        <a href="{{ route('platform.companies.index') }}">Companies</a>
        <span aria-hidden="true">/</span>
        <span>{{ $company->name }}</span>
    </div>

    <div class="platform-page-heading">
        <div class="platform-company-title">
            <span class="platform-company-avatar platform-company-avatar-large" aria-hidden="true">{{ mb_strtoupper(mb_substr($company->name, 0, 1)) }}</span>
            <div>
                <p class="platform-eyebrow">Company details</p>
                <h1>{{ $company->name }}</h1>
                <p class="platform-page-subtitle">{{ $company->slug }} · Created {{ $company->created_at->format('F j, Y') }}</p>
            </div>
        </div>
        <span @class(['platform-status platform-status-large', 'status-active' => $company->status === \App\Models\Company::STATUS_ACTIVE, 'status-suspended' => $company->status === \App\Models\Company::STATUS_SUSPENDED])>
            {{ ucfirst($company->status) }}
        </span>
    </div>

    <section class="platform-detail-stat-grid" aria-label="Company statistics">
        <article class="platform-stat-card"><span class="platform-stat-label">Staff</span><strong>{{ number_format($company->staff_count) }}</strong><small>Assigned staff accounts</small></article>
        <article class="platform-stat-card"><span class="platform-stat-label">Company admins</span><strong>{{ number_format($company->admin_count) }}</strong><small>Assigned administrator accounts</small></article>
        <article class="platform-stat-card"><span class="platform-stat-label">Attendance records</span><strong>{{ number_format($company->attendances_count) }}</strong><small>Owned by this company</small></article>
        <article class="platform-stat-card"><span class="platform-stat-label">Pending admin invites</span><strong>{{ number_format($pendingAdminInvitations) }}</strong><small>Unexpired invitations</small></article>
    </section>

    <div class="platform-detail-grid">
        <section class="platform-panel">
            <div class="platform-panel-heading">
                <div>
                    <h2>Company profile</h2>
                    <p>Update the company’s display name or URL slug.</p>
                </div>
            </div>
            <form class="platform-detail-form" method="POST" action="{{ route('platform.companies.update', $company) }}">
                @csrf
                @method('PATCH')
                <div class="platform-form-field">
                    <label for="company-name">Company name</label>
                    <input id="company-name" name="name" type="text" value="{{ old('name', $company->name) }}" maxlength="255" required>
                    @error('name')<span class="platform-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="platform-form-field">
                    <label for="company-slug">Company slug</label>
                    <input id="company-slug" name="slug" type="text" value="{{ old('slug', $company->slug) }}" maxlength="255" pattern="[A-Za-z0-9_-]+" required>
                    @error('slug')<span class="platform-field-error">{{ $message }}</span>@enderror
                </div>
                <button class="platform-button platform-button-primary" type="submit">Save profile</button>
            </form>
        </section>

        <section class="platform-panel">
            <div class="platform-panel-heading">
                <div>
                    <h2>Company access</h2>
                    <p>Suspending blocks company users from protected application areas.</p>
                </div>
            </div>
            <div class="platform-access-state">
                <span @class(['platform-status', 'status-active' => $company->status === \App\Models\Company::STATUS_ACTIVE, 'status-suspended' => $company->status === \App\Models\Company::STATUS_SUSPENDED])>
                    {{ ucfirst($company->status) }}
                </span>
                <p>
                    @if ($company->status === \App\Models\Company::STATUS_ACTIVE)
                        Company-admin invitations and company access are enabled.
                    @else
                        Company access is suspended. Existing data is preserved.
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('platform.companies.update', $company) }}"
                data-confirm="{{ $company->status === \App\Models\Company::STATUS_ACTIVE ? 'Suspend this company? Company users will lose access to protected pages and APIs.' : 'Reactivate this company and restore company-user access?' }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $company->status === \App\Models\Company::STATUS_ACTIVE ? 'suspended' : 'active' }}">
                <button @class(['platform-button', 'platform-button-danger' => $company->status === \App\Models\Company::STATUS_ACTIVE, 'platform-button-primary' => $company->status === \App\Models\Company::STATUS_SUSPENDED]) type="submit">
                    {{ $company->status === \App\Models\Company::STATUS_ACTIVE ? 'Suspend company' : 'Reactivate company' }}
                </button>
            </form>

            <div class="mt-6 border-t border-slate-200 pt-5">
                <h3 class="font-semibold text-slate-900">AI Features (AaaS)</h3>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $company->ai_enabled ? 'AI assistant and report drafting are enabled for this organization.' : 'AI assistant and report drafting are disabled for this organization.' }}
                </p>
                <form method="POST" action="{{ route('platform.companies.update', $company) }}" class="mt-3">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="ai_enabled" value="{{ $company->ai_enabled ? '0' : '1' }}">
                    <button class="platform-button {{ $company->ai_enabled ? 'platform-button-danger' : 'platform-button-primary' }}" type="submit">
                        {{ $company->ai_enabled ? 'Disable AI features' : 'Enable AI features' }}
                    </button>
                </form>
            </div>
        </section>

        <section class="platform-panel platform-invite-panel">
            <div class="platform-panel-heading">
                <div>
                    <h2>Invite a company admin</h2>
                    <p>The invitation is issued for this company and grants the admin role after registration.</p>
                </div>
            </div>
            @if ($company->status !== \App\Models\Company::STATUS_ACTIVE)
                <div class="platform-inline-notice" role="status">Reactivate this company before sending administrator invitations.</div>
            @else
                <form class="platform-invite-form" method="POST" action="{{ route('platform.companies.admin-invitations.store', $company) }}">
                    @csrf
                    <div class="platform-form-field">
                        <label for="admin-email">Administrator email</label>
                        <input id="admin-email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required placeholder="admin@example.com">
                        @error('email')<span class="platform-field-error">{{ $message }}</span>@enderror
                    </div>
                    <button class="platform-button platform-button-primary" type="submit">Send invitation</button>
                </form>
                <p class="platform-form-help">Invitation tokens are sent by email and are not displayed in this dashboard.</p>
            @endif

            <div class="mt-8 border-t border-slate-200 pt-6">
                <div class="platform-panel-heading">
                    <div>
                        <h2>Sent administrator invitations</h2>
                        <p>Track each invite and the company name registered during onboarding.</p>
                    </div>
                </div>
                @if ($adminInvitations->isEmpty())
                    <p class="text-sm text-slate-500">No administrator invitations have been sent for this company.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="py-3 pr-4">Email</th>
                                    <th class="py-3 pr-4">Company Name</th>
                                    <th class="py-3 pr-4">Sent</th>
                                    <th class="py-3 pr-4">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($adminInvitations as $adminInvitation)
                                    @php
                                        $registeredAdmin = $companyAdminsByEmail->get($adminInvitation->email);
                                        if ($adminInvitation->used_at !== null) {
                                            $invitationStatus = $registeredAdmin?->is_active ? 'Completed / Active' : 'Completed';
                                            $invitationStatusClass = $registeredAdmin?->is_active
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-slate-100 text-slate-700';
                                        } elseif ($adminInvitation->expires_at->isPast()) {
                                            $invitationStatus = 'Expired';
                                            $invitationStatusClass = 'bg-rose-100 text-rose-800';
                                        } else {
                                            $invitationStatus = 'Pending';
                                            $invitationStatusClass = 'bg-amber-100 text-amber-800';
                                        }
                                    @endphp
                                    <tr>
                                        <td class="py-3 pr-4 font-medium text-slate-800">{{ $adminInvitation->email }}</td>
                                        <td class="py-3 pr-4 text-slate-600">
                                            {{ $registeredAdmin ? $company->name : '—' }}
                                        </td>
                                        <td class="py-3 pr-4 text-slate-500">{{ $adminInvitation->created_at->format('M j, Y') }}</td>
                                        <td class="py-3 pr-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $invitationStatusClass }}">
                                                {{ $invitationStatus }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
