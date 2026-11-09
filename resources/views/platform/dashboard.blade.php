@extends('platform.layout')

@section('title', 'Platform overview')

@section('content')
    <div class="platform-page-heading">
        <div>
            <p class="platform-eyebrow">Platform overview</p>
            <h1>Good {{ now()->format('H') < 12 ? 'morning' : (now()->format('H') < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}</h1>
            <p class="platform-page-subtitle">A current view of companies and activity across the attendance platform.</p>
        </div>
        <a class="platform-button platform-button-primary" href="{{ route('platform.companies.index') }}">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
            </svg>
            Manage companies
        </a>
    </div>

    <section class="platform-stat-grid" aria-label="Platform statistics">
        <article class="platform-stat-card">
            <span class="platform-stat-icon icon-violet" aria-hidden="true">C</span>
            <span class="platform-stat-label">Total companies</span>
            <strong>{{ number_format($stats['companies']) }}</strong>
            <small>Registered on the platform</small>
        </article>
        <article class="platform-stat-card">
            <span class="platform-stat-icon icon-green" aria-hidden="true">A</span>
            <span class="platform-stat-label">Active companies</span>
            <strong>{{ number_format($stats['active_companies']) }}</strong>
            <small>Currently enabled</small>
        </article>
        <article class="platform-stat-card">
            <span class="platform-stat-icon icon-amber" aria-hidden="true">S</span>
            <span class="platform-stat-label">Suspended companies</span>
            <strong>{{ number_format($stats['suspended_companies']) }}</strong>
            <small>Access is restricted</small>
        </article>
        <article class="platform-stat-card">
            <span class="platform-stat-icon icon-blue" aria-hidden="true">T</span>
            <span class="platform-stat-label">Assigned staff</span>
            <strong>{{ number_format($stats['staff']) }}</strong>
            <small>Staff with company ownership</small>
        </article>
        <article class="platform-stat-card">
            <span class="platform-stat-icon icon-coral" aria-hidden="true">D</span>
            <span class="platform-stat-label">Company admins</span>
            <strong>{{ number_format($stats['company_admins']) }}</strong>
            <small>Assigned admin accounts</small>
        </article>
        <article class="platform-stat-card">
            <span class="platform-stat-icon icon-cyan" aria-hidden="true">✓</span>
            <span class="platform-stat-label">Attendance today</span>
            <strong>{{ number_format($stats['attendance_today']) }}</strong>
            <small>Records for today</small>
        </article>
        <article class="platform-stat-card">
            <span class="platform-stat-icon icon-slate" aria-hidden="true">I</span>
            <span class="platform-stat-label">Pending admin invites</span>
            <strong>{{ number_format($stats['pending_admin_invitations']) }}</strong>
            <small>Unexpired company-admin invitations</small>
        </article>
    </section>

    <section class="platform-panel-grid">
        <article class="platform-panel">
            <div class="platform-panel-heading">
                <div>
                    <h2>Recently added companies</h2>
                    <p>Latest companies registered on the platform.</p>
                </div>
                <a class="platform-text-link" href="{{ route('platform.companies.index') }}">View all</a>
            </div>

            @forelse ($recentCompanies as $company)
                <a class="platform-company-row" href="{{ route('platform.companies.show', $company) }}">
                    <span class="platform-company-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($company->name, 0, 1)) }}</span>
                    <span class="platform-company-info">
                        <strong>{{ $company->name }}</strong>
                        <small>{{ $company->slug }} · {{ number_format($company->staff_count) }} {{ \Illuminate\Support\Str::plural('staff member', $company->staff_count) }}</small>
                    </span>
                    <span @class(['platform-status', 'status-active' => $company->status === \App\Models\Company::STATUS_ACTIVE, 'status-suspended' => $company->status === \App\Models\Company::STATUS_SUSPENDED])>
                        {{ ucfirst($company->status) }}
                    </span>
                    <span class="platform-row-date">{{ $company->created_at->format('M j, Y') }}</span>
                </a>
            @empty
                <div class="platform-empty-state">
                    <span class="platform-empty-icon" aria-hidden="true">C</span>
                    <strong>No companies yet</strong>
                    <p>Create a company to begin onboarding organizations.</p>
                    <a class="platform-text-link" href="{{ route('platform.companies.index') }}">Open company management</a>
                </div>
            @endforelse
        </article>

        <article class="platform-panel">
            <div class="platform-panel-heading">
                <div>
                    <h2>Platform activity</h2>
                    <p>Ownership changes recorded by the platform.</p>
                </div>
                @if ($hasAuditLog)
                    <span class="platform-live-label"><span></span> Audit log active</span>
                @endif
            </div>

            @if (! $hasAuditLog)
                <div class="platform-empty-state platform-empty-compact">
                    <span class="platform-empty-icon" aria-hidden="true">i</span>
                    <strong>Audit history is not configured</strong>
                    <p>Ownership activity will appear here after the platform audit migration is installed.</p>
                </div>
            @elseif ($recentActivity->isEmpty())
                <div class="platform-empty-state platform-empty-compact">
                    <span class="platform-empty-icon" aria-hidden="true">✓</span>
                    <strong>No ownership activity yet</strong>
                    <p>Recorded legacy ownership changes will be shown here.</p>
                </div>
            @else
                <div class="platform-activity-list">
                    @foreach ($recentActivity as $activity)
                        <div class="platform-activity-item">
                            <span class="platform-activity-dot" aria-hidden="true"></span>
                            <div>
                                <strong>{{ ucfirst(str_replace('_', ' ', \Illuminate\Support\Str::singular($activity->record_type))) }} #{{ $activity->record_id }} assigned</strong>
                                <p>{{ $activity->company_name }} · operator {{ $activity->operator }}</p>
                                <time datetime="{{ \Illuminate\Support\Carbon::parse($activity->created_at)->toIso8601String() }}">
                                    {{ \Illuminate\Support\Carbon::parse($activity->created_at)->diffForHumans() }}
                                </time>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>
@endsection
