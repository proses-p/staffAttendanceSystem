@extends('platform.layout')

@section('title', 'Companies')

@section('content')
    <div class="platform-page-heading">
        <div>
            <p class="platform-eyebrow">Platform management</p>
            <h1>Companies</h1>
            <p class="platform-page-subtitle">Create and manage company access and administrator onboarding.</p>
        </div>
        <span class="platform-count-chip">{{ number_format($totalCompanies) }} total</span>
    </div>

    <section class="platform-panel platform-create-panel">
        <div class="platform-panel-heading">
            <div>
                <h2>Create a company</h2>
                <p>Company names and URL slugs must be unique and verified.</p>
            </div>
        </div>
        <form class="platform-company-create-form" method="POST" action="{{ route('platform.companies.store') }}">
            @csrf
            <div class="platform-form-field">
                <label for="company-name">Company name</label>
                <input id="company-name" name="name" type="text" value="{{ old('name') }}" maxlength="255" required autocomplete="organization" placeholder="e.g. Acme Services">
                @error('name')<span class="platform-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="platform-form-field">
                <label for="company-slug">Company slug</label>
                <input id="company-slug" name="slug" type="text" value="{{ old('slug') }}" maxlength="255" pattern="[A-Za-z0-9_-]+" required autocomplete="off" placeholder="acme-services">
                <small>Letters, numbers, hyphens and underscores.</small>
                @error('slug')<span class="platform-field-error">{{ $message }}</span>@enderror
            </div>
            <button class="platform-button platform-button-primary platform-create-submit" type="submit">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                Create company
            </button>
        </form>
    </section>

    <section class="platform-panel platform-company-list-panel">
        <div class="platform-panel-heading">
            <div>
                <h2>Company directory</h2>
                <p>{{ number_format($companies->total()) }} {{ \Illuminate\Support\Str::plural('result', $companies->total()) }}</p>
            </div>
        </div>

        <form class="platform-filter-form" method="GET" action="{{ route('platform.companies.index') }}">
            <div class="platform-search-field">
                <label class="sr-only" for="company-search">Search companies</label>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="11" cy="11" r="7"></circle><path stroke-linecap="round" d="m16 16 4 4"></path>
                </svg>
                <input id="company-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or slug">
            </div>
            <div class="platform-form-field platform-filter-status">
                <label class="sr-only" for="company-status">Filter by status</label>
                <select id="company-status" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option>
                </select>
            </div>
            <button class="platform-button platform-button-secondary" type="submit">Apply filters</button>
            @if (($filters['search'] ?? '') || ($filters['status'] ?? ''))
                <a class="platform-clear-link" href="{{ route('platform.companies.index') }}">Clear</a>
            @endif
        </form>

        @if ($companies->isEmpty())
            <div class="platform-empty-state">
                <span class="platform-empty-icon" aria-hidden="true">⌕</span>
                <strong>{{ $totalCompanies === 0 ? 'No companies have been created' : 'No companies match these filters' }}</strong>
                <p>{{ $totalCompanies === 0 ? 'Use the form above to create the first verified company.' : 'Try a different search term or clear your filters.' }}</p>
                @if ($totalCompanies > 0)
                    <a class="platform-text-link" href="{{ route('platform.companies.index') }}">Clear filters</a>
                @endif
            </div>
        @else
            <div class="platform-company-list">
                @foreach ($companies as $company)
                    <article class="platform-directory-card">
                        <span class="platform-company-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($company->name, 0, 1)) }}</span>
                        <div class="platform-directory-main">
                            <a class="platform-company-name" href="{{ route('platform.companies.show', $company) }}">{{ $company->name }}</a>
                            <span class="platform-company-slug">{{ $company->slug }}</span>
                            <span class="platform-directory-date">Created {{ $company->created_at->format('M j, Y') }}</span>
                        </div>
                        <div class="platform-directory-counts">
                            <span><strong>{{ number_format($company->staff_count) }}</strong> staff</span>
                            <span><strong>{{ number_format($company->admin_count) }}</strong> admins</span>
                        </div>
                        <span @class(['platform-status', 'status-active' => $company->status === \App\Models\Company::STATUS_ACTIVE, 'status-suspended' => $company->status === \App\Models\Company::STATUS_SUSPENDED])>
                            {{ ucfirst($company->status) }}
                        </span>
                        <form method="POST" action="{{ route('platform.companies.update', $company) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="ai_enabled" value="{{ $company->ai_enabled ? '0' : '1' }}">
                            <button type="submit" class="platform-button platform-button-small {{ $company->ai_enabled ? 'platform-button-secondary' : 'platform-button-primary' }}" aria-label="{{ $company->ai_enabled ? 'Disable AI for' : 'Enable AI for' }} {{ $company->name }}">
                                AI {{ $company->ai_enabled ? 'On' : 'Off' }}
                            </button>
                        </form>
                        <a class="platform-button platform-button-small platform-button-secondary" href="{{ route('platform.companies.show', $company) }}">Manage</a>
                    </article>
                @endforeach
            </div>

            @if ($companies->hasPages())
                <div class="platform-pagination">{{ $companies->links() }}</div>
            @endif
        @endif
    </section>
@endsection
