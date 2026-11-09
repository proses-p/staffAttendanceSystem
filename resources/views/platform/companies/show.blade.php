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
        </section>
    </div>
@endsection
