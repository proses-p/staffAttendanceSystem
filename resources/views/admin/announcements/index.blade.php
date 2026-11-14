<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notice Board · StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
    <div class="min-h-screen flex flex-col lg:flex-row">
        @include('components.admin-sidebar')
        <main class="min-w-0 flex-1 scroll-mt-0 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl space-y-6">
                <header>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">Company communication</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">Notice Board</h1>
                    <p class="mt-2 text-sm text-slate-500">Publish important updates for your active staff.</p>
                </header>

                @if (session('success'))
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold">Publish an announcement</h2>
                        <p class="mt-1 text-sm text-slate-500">Active staff will receive an instant notification.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.announcements.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="title" class="mb-1.5 block text-sm font-medium text-slate-700">Title</label>
                            <input id="title" name="title" type="text" maxlength="255" required value="{{ old('title') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-cyan-600 focus:outline-none focus:ring-4 focus:ring-cyan-100">
                        </div>
                        <div>
                            <label for="content" class="mb-1.5 block text-sm font-medium text-slate-700">Message</label>
                            <textarea id="content" name="content" rows="5" maxlength="20000" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-cyan-600 focus:outline-none focus:ring-4 focus:ring-cyan-100">{{ old('content') }}</textarea>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="priority" class="mb-1.5 block text-sm font-medium text-slate-700">Priority</label>
                                <select id="priority" name="priority" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
                                    <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                                    <option value="important" @selected(old('priority') === 'important')>Important</option>
                                    <option value="urgent" @selected(old('priority') === 'urgent')>Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label for="expires_at" class="mb-1.5 block text-sm font-medium text-slate-700">Expiry date <span class="font-normal text-slate-400">(optional)</span></label>
                                <input id="expires_at" name="expires_at" type="date" min="{{ today()->toDateString() }}" value="{{ old('expires_at') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                            </div>
                            <label class="flex items-center gap-2 self-end pb-3 text-sm font-medium text-slate-700">
                                <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned')) class="h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">
                                Pin announcement
                            </label>
                        </div>
                        <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-semibold text-white hover:bg-cyan-800">Publish announcement</button>
                    </form>
                </section>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold">Company announcements</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $announcements->count() }} total · {{ $activeStaffCount }} active staff</p>
                        </div>
                    </div>
                    @forelse ($announcements as $announcement)
                        @php
                            $priorityClasses = match ($announcement->priority) {
                                'urgent' => 'bg-rose-100 text-rose-800',
                                'important' => 'bg-amber-100 text-amber-800',
                                default => 'bg-sky-100 text-sky-800',
                            };
                            $expired = $announcement->expires_at?->isBefore(today()) ?? false;
                        @endphp
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                <div class="min-w-0 flex-1">
                                    <div class="mb-3 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase {{ $priorityClasses }}">{{ ucfirst($announcement->priority) }}</span>
                                        @if ($announcement->is_pinned)
                                            <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-bold text-cyan-800">PINNED</span>
                                        @endif
                                        @if ($expired)
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Expired</span>
                                        @elseif ($announcement->expires_at)
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">Expires {{ $announcement->expires_at->format('M j, Y') }}</span>
                                        @endif
                                    </div>
                                    <h3 class="text-lg font-semibold">{{ $announcement->title }}</h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        By {{ $announcement->author?->name ?? 'Former admin' }} · {{ $announcement->created_at->format('M j, Y · g:i A') }}
                                    </p>
                                    <div class="mt-4 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{{ $announcement->content }}</div>
                                    <p class="mt-4 text-xs font-semibold text-slate-500">
                                        Read by {{ $announcement->staff_read_count }} / {{ $activeStaffCount }} staff
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2 sm:flex-col">
                                    <form method="POST" action="{{ route('admin.announcements.pin', $announcement) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            {{ $announcement->is_pinned ? 'Unpin' : 'Pin' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                            No announcements yet. Publish one above to share an update with your staff.
                        </div>
                    @endforelse
                </section>
            </div>
        </main>
    </div>
</body>
</html>
