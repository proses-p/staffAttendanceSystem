<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Platform') · StaffFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="platform-body">
    <div class="platform-shell">
        <button
            class="platform-sidebar-overlay"
            id="platform-sidebar-overlay"
            type="button"
            data-sidebar-close
            aria-label="Close navigation"
        ></button>

        <aside class="platform-sidebar" id="platform-sidebar" aria-label="Platform navigation">
            <a class="platform-brand" href="{{ route('platform.dashboard') }}">
                <span class="platform-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 20V9l8-5 8 5v11M8 20v-6h8v6M2 20h20" />
                    </svg>
                </span>
                <span>
                    <strong>StaffFlow</strong>
                    <small>Platform console</small>
                </span>
            </a>

            <p class="platform-nav-label">Platform</p>
            <nav class="platform-nav">
                <a href="{{ route('platform.dashboard') }}" @class(['is-active' => request()->routeIs('platform.dashboard')])>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h7V3H3v10Zm0 8h7v-5H3v5Zm11 0h7V11h-7v10Zm0-14h7V3h-7v4Z" />
                    </svg>
                    <span>Overview</span>
                </a>
                <a href="{{ route('platform.companies.index') }}" @class(['is-active' => request()->routeIs('platform.companies.*')])>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M8 9v.01M8 12v.01M8 15v.01M8 18v.01M15 12v.01M15 15v.01M15 18v.01" />
                    </svg>
                    <span>Companies</span>
                </a>
            </nav>

            <div class="platform-sidebar-spacer"></div>
            <div class="platform-admin-card">
                <span class="platform-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="platform-admin-copy">
                    <strong>{{ auth()->user()->name }}</strong>
                    <small>Super Admin</small>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="platform-icon-button" type="submit" aria-label="Sign out" title="Sign out">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
                        </svg>
                    </button>
                </form>
            </div>
        </aside>

        <div class="platform-main">
            <header class="platform-topbar">
                <button
                    class="platform-menu-button"
                    type="button"
                    data-sidebar-toggle
                    aria-controls="platform-sidebar"
                    aria-expanded="false"
                    aria-label="Open navigation"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="platform-topbar-context">
                    <span>Staff Attendance System</span>
                    <span class="platform-topbar-separator">/</span>
                    <strong>Platform administration</strong>
                </div>
                <span class="platform-secure-label">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 10V7a5 5 0 0 1 10 0v3m-11 0h12a2 2 0 0 1 2 2v8H4v-8a2 2 0 0 1 2-2Z" />
                    </svg>
                    Restricted access
                </span>
            </header>

            <main class="platform-content">
                @if (session('success'))
                    <div class="platform-alert platform-alert-success" role="status">
                        <span>{{ session('success') }}</span>
                        <button type="button" data-dismiss-alert aria-label="Dismiss notification">&times;</button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="platform-alert platform-alert-error" role="alert">
                        <div>
                            <strong>Please review the following:</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <button type="button" data-dismiss-alert aria-label="Dismiss errors">&times;</button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
