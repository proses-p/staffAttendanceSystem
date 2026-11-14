<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Work Reports · StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
    <div class="min-h-screen flex flex-col lg:flex-row">
        @include('components.admin-sidebar')

        <main class="min-w-0 flex-1 scroll-mt-0 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-600">Staff activity</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Daily Work Reports</h1>
                        <p class="mt-2 text-sm text-slate-500">Review staff achievements, photos, and requested follow-ups.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('assignTaskModal').classList.remove('hidden')" class="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">
                        Assign New Task
                    </button>
                </header>

                @if (session('success'))
                    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <form method="GET" action="{{ route('admin.daily-work.index') }}" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
                    <div>
                        <label for="filter-user" class="mb-1.5 block text-sm font-medium text-slate-700">Staff member</label>
                        <select id="filter-user" name="user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All staff</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $member->id)>{{ $member->name }} · {{ $member->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filter-date" class="mb-1.5 block text-sm font-medium text-slate-700">Submission date</label>
                        <input id="filter-date" type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Filter</button>
                    <a href="{{ route('admin.daily-work.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
                </form>

                <div class="space-y-5">
                    @forelse ($submissions as $submission)
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-950">{{ $submission->staff?->name ?? 'Staff member' }}</h2>
                                    <p class="mt-1 text-sm text-slate-500">{{ $submission->staff?->email }} · {{ $submission->submission_date->format('d M Y') }}</p>
                                    @if ($submission->assignedTask)
                                        <p class="mt-2 text-sm text-cyan-700">Task: {{ $submission->assignedTask->title }}</p>
                                    @endif
                                </div>
                                @php
                                    $reviewBadge = match ($submission->review_status) {
                                        'accepted' => 'bg-emerald-50 text-emerald-700',
                                        'revision_requested' => 'bg-amber-50 text-amber-800',
                                        default => 'bg-blue-50 text-blue-700',
                                    };
                                @endphp
                                <span class="w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $reviewBadge }}">{{ str_replace('_', ' ', ucfirst($submission->review_status)) }}</span>
                            </div>

                            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_1fr]">
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed part / milestone</p>
                                        <p class="mt-1 font-medium text-slate-900">{{ $submission->completed_part }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Work description</p>
                                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $submission->description }}</p>
                                    </div>
                                    <p class="text-sm text-slate-600">Work status: <span class="font-semibold">{{ str_replace('_', ' ', ucfirst($submission->work_status)) }}</span></p>
                                    @if ($submission->admin_feedback)
                                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Admin feedback</p>
                                            <p class="mt-1 whitespace-pre-line text-sm text-amber-900">{{ $submission->admin_feedback }}</p>
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Photos</p>
                                    @if ($submission->photos->isEmpty())
                                        <p class="text-sm text-slate-400">No photos attached.</p>
                                    @else
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            @foreach ($submission->photos as $photo)
                                                <a href="{{ $photo->url }}" target="_blank" rel="noopener noreferrer" class="overflow-hidden rounded-lg border border-slate-200">
                                                    <img src="{{ $photo->url }}" alt="Daily work photo" class="h-28 w-full object-cover transition hover:scale-105">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/70 p-4 sm:flex-row sm:items-end">
                                <form method="POST" action="{{ route('admin.daily-work.accept', $submission) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Accept / Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.daily-work.request-revision', $submission) }}" class="flex flex-1 flex-col gap-2 sm:flex-row">
                                    @csrf
                                    <label class="sr-only" for="feedback-{{ $submission->id }}">Revision feedback (required)</label>
                                    <textarea id="feedback-{{ $submission->id }}" name="admin_feedback" required maxlength="5000" rows="2" placeholder="Revision feedback (required)" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                                    <button type="submit" class="rounded-lg border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50">Request Revision</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">No daily work submissions match these filters.</div>
                    @endforelse
                </div>

                <div class="mt-6">{{ $submissions->links() }}</div>
            </div>
        </main>
    </div>

    <div id="assignTaskModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if(event.target===this)this.classList.add('hidden')">
        <section role="dialog" aria-modal="true" aria-labelledby="assignTaskTitle" class="relative my-auto max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between">
                <h2 id="assignTaskTitle" class="text-xl font-semibold text-slate-950">Assign New Task</h2>
                <button type="button" aria-label="Close" onclick="document.getElementById('assignTaskModal').classList.add('hidden')" class="rounded-lg px-3 py-1 text-xl text-slate-500 hover:bg-slate-100">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.daily-work.tasks.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="task-staff" class="mb-1.5 block text-sm font-medium text-slate-700">Assign to staff</label>
                    <select id="task-staff" name="user_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select a staff member</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}">{{ $member->name }} · {{ $member->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="task-title" class="mb-1.5 block text-sm font-medium text-slate-700">Task title</label>
                    <input id="task-title" name="title" required maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="task-instructions" class="mb-1.5 block text-sm font-medium text-slate-700">Instructions (optional)</label>
                    <textarea id="task-instructions" name="instructions" rows="3" maxlength="10000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
                <div>
                    <label for="task-due-date" class="mb-1.5 block text-sm font-medium text-slate-700">Due date (optional)</label>
                    <input id="task-due-date" type="date" name="due_date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('assignTaskModal').classList.add('hidden')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">Assign task</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
