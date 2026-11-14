<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense claim #{{ $expense->id }} · StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
<div class="min-h-screen flex flex-col lg:flex-row">
    @include('components.admin-sidebar')
    <main class="min-w-0 flex-1 scroll-mt-0 p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-7xl">
            <a href="{{ route('admin.field-expenses.index') }}" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Pending Field Reimbursements</a>
            <header class="mb-6 flex flex-col gap-3 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Claim #{{ $expense->id }} · Assignment #{{ $expense->field_assignment_id }}</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Expense review</h1>
                    <p class="mt-2 text-sm text-slate-500">Evidence should inform the decision; GPS risk indicators do not automatically determine claim eligibility.</p>
                </div>
                <span class="w-fit rounded-full px-3 py-1.5 text-sm font-semibold {{ $expense->status === 'pending' ? 'bg-amber-100 text-amber-800' : ($expense->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') }}">{{ ucfirst($expense->status) }}</span>
            </header>

            @if (session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p class="font-semibold">The review could not be submitted.</p>
                    <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="grid items-start gap-5 lg:grid-cols-2">
                <section class="space-y-5">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">Field assignment evidence</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div><p class="text-xs font-semibold uppercase text-slate-500">Employee</p><p class="mt-1 font-medium">{{ $expense->user?->name ?? 'Unknown employee' }}</p><p class="text-sm text-slate-500">{{ $expense->user?->employee_id ?: 'User #'.$expense->user_id }} · {{ $expense->user?->organization ?: ($expense->user?->company?->name ?? 'Organization unavailable') }}</p></div>
                            <div><p class="text-xs font-semibold uppercase text-slate-500">Task type</p><p class="mt-1 font-medium">{{ str_replace('_', ' ', $assignment?->task_type ?? 'Not available') }}</p></div>
                            <div><p class="text-xs font-semibold uppercase text-slate-500">Clock-in</p><p class="mt-1">{{ $assignment?->clock_in_at?->format('d M Y, H:i:s') ?? 'Not available' }}</p></div>
                            <div><p class="text-xs font-semibold uppercase text-slate-500">Clock-out</p><p class="mt-1">{{ $assignment?->clock_out_at?->format('d M Y, H:i:s') ?? 'Not available' }}</p></div>
                            <div><p class="text-xs font-semibold uppercase text-slate-500">Elapsed duration</p><p class="mt-1">{{ $elapsedSeconds === null ? 'In progress / unavailable' : intdiv($elapsedSeconds, 3600).'h '.intdiv($elapsedSeconds % 3600, 60).'m' }}</p></div>
                            <div><p class="text-xs font-semibold uppercase text-slate-500">Last location update</p><p class="mt-1">{{ $assignment?->last_location_at?->format('d M Y, H:i:s') ?? 'Not available' }}</p></div>
                        </div>
                        <div class="mt-4 rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase text-slate-500">Assignment purpose</p>
                            <p class="mt-1 text-sm leading-6">{{ $assignment?->purpose_description ?: 'No purpose description was recorded.' }}</p>
                        </div>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs font-semibold uppercase text-slate-500">Clock-in GPS</dt><dd class="mt-1">{{ $assignment?->clock_in_lat !== null && $assignment?->clock_in_lng !== null ? $assignment->clock_in_lat.', '.$assignment->clock_in_lng : 'Unavailable' }}</dd></div>
                            <div><dt class="text-xs font-semibold uppercase text-slate-500">Clock-out GPS</dt><dd class="mt-1">{{ $assignment?->clock_out_lat !== null && $assignment?->clock_out_lng !== null ? $assignment->clock_out_lat.', '.$assignment->clock_out_lng : 'Unavailable' }}</dd></div>
                            <div><dt class="text-xs font-semibold uppercase text-slate-500">Last known location</dt><dd class="mt-1">{{ $assignment?->last_lat !== null && $assignment?->last_lng !== null ? $assignment->last_lat.', '.$assignment->last_lng : 'Unavailable' }}</dd></div>
                            <div><dt class="text-xs font-semibold uppercase text-slate-500">GPS accuracy</dt><dd class="mt-1">{{ $assignment?->gps_accuracy !== null ? number_format((float) $assignment->gps_accuracy, 1).' m' : 'Unavailable' }}</dd></div>
                        </dl>
                        @if ($assignment?->clock_in_lat !== null || $assignment?->clock_out_lat !== null || $assignment?->last_lat !== null)
                            <div id="assignmentMap" class="mt-4 h-64 overflow-hidden rounded-xl border border-slate-200" aria-label="Assignment GPS locations"></div>
                        @endif
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">Location risk indicators</h2>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $assignment?->is_mock_location ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">Mock location: {{ $assignment?->is_mock_location ? 'Detected' : 'Not detected' }}</span>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $assignment?->is_flagged ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">Flagged: {{ $assignment?->is_flagged ? 'Yes' : 'No' }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Risk score: {{ $assignment?->risk_score ?? 'Unavailable' }}</span>
                        </div>
                        @if (count($assignment?->flag_reasons ?? []))
                            <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-slate-700">
                                @foreach ($assignment->flag_reasons as $reason)<li>{{ is_scalar($reason) ? $reason : json_encode($reason) }}</li>@endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-sm text-slate-500">No flag reasons were recorded.</p>
                        @endif
                    </article>
                </section>

                <section class="space-y-5">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">Financial claim</h2>
                        <div class="mt-4 rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase text-slate-500">Claimed amount</p>
                            <p class="mt-1 text-3xl font-bold tracking-tight text-slate-950">{{ $expense->currency }} {{ number_format((float) $expense->amount, 2) }}</p>
                        </div>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div><dt class="text-xs font-semibold uppercase text-slate-500">Category</dt><dd class="mt-1 font-medium">{{ ['transport_fuel' => 'Transport / Fuel', 'meals_refreshments' => 'Meals / Refreshments', 'client_entertainment' => 'Client Entertainment', 'other_emergency' => 'Other / Emergency'][$expense->category] ?? $expense->category }}</dd></div>
                            <div><dt class="text-xs font-semibold uppercase text-slate-500">Submitted</dt><dd class="mt-1">{{ $expense->created_at?->format('d M Y, H:i:s') }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-slate-500">Description</dt><dd class="mt-1 whitespace-pre-line text-sm leading-6">{{ $expense->description ?: 'No description supplied.' }}</dd></div>
                        </dl>
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <p class="text-xs font-semibold uppercase text-slate-500">Receipt</p>
                            @if ($expense->receipt_image_path)
                                <a href="{{ route('admin.field-expenses.receipt', $expense) }}" target="_blank" rel="noopener" class="mt-3 inline-flex text-sm font-semibold text-cyan-700 hover:text-cyan-900">Open secure receipt image ↗</a>
                                <img src="{{ route('admin.field-expenses.receipt', $expense) }}" alt="Receipt for expense claim #{{ $expense->id }}" class="mt-3 max-h-80 w-full rounded-xl border border-slate-200 bg-slate-50 object-contain" onerror="this.replaceWith(Object.assign(document.createElement('p'),{className:'mt-3 text-sm text-slate-500',textContent:'Receipt image is unavailable.'}))">
                            @else
                                <p class="mt-2 text-sm text-slate-500">No receipt was attached.</p>
                            @endif
                        </div>
                        @if ($expense->status === 'pending')
                            <div class="mt-6 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                                <form method="POST" action="{{ route('admin.field-expenses.approve', $expense) }}" onsubmit="return confirmReview(this, 'Approve this expense claim?')">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Approve claim</button>
                                </form>
                                <button type="button" onclick="document.getElementById('rejectDialog').showModal()" class="rounded-lg bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800">Reject claim</button>
                            </div>
                        @endif
                        @if ($expense->status !== 'pending')
                            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                <p class="font-semibold">{{ ucfirst($expense->status) }} by {{ $expense->reviewer?->name ?? 'Reviewer unavailable' }}</p>
                                <p class="mt-1 text-slate-600">{{ $expense->reviewed_at?->format('d M Y, H:i:s') }}</p>
                                @if ($expense->admin_notes)<p class="mt-2 whitespace-pre-line">{{ $expense->admin_notes }}</p>@endif
                            </div>
                        @endif
                    </article>

                    @if ($expense->reviewAudits->isNotEmpty())
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="text-lg font-semibold">Review audit history</h2>
                            <ol class="mt-4 space-y-4">
                                @foreach ($expense->reviewAudits->sortBy('reviewed_at') as $audit)
                                    <li class="border-l-2 border-cyan-200 pl-4">
                                        <p class="font-semibold">{{ ucfirst($audit->action) }} · {{ $audit->previous_status }} → {{ $audit->new_status }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $audit->reviewer?->name ?? 'Reviewer unavailable' }} · {{ $audit->reviewed_at?->format('d M Y, H:i:s') }}</p>
                                        @if ($audit->admin_notes)<p class="mt-2 whitespace-pre-line text-sm">{{ $audit->admin_notes }}</p>@endif
                                    </li>
                                @endforeach
                            </ol>
                        </article>
                    @endif
                </section>
            </div>
        </div>
    </main>
</div>

@if ($expense->status === 'pending')
    <dialog id="rejectDialog" class="m-auto max-h-[90vh] w-[min(94vw,32rem)] overflow-y-auto rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/60" @if ($errors->has('admin_notes')) open @endif>
        <form method="POST" action="{{ route('admin.field-expenses.reject', $expense) }}" class="p-6" onsubmit="return submitReview(this)">
            @csrf
            <h2 class="text-xl font-semibold">Reject expense claim</h2>
            <p class="mt-2 text-sm text-slate-500">A clear explanation is required and will be included in the review audit.</p>
            <label for="admin_notes" class="mt-5 block text-sm font-semibold">Rejection reason</label>
            <textarea id="admin_notes" name="admin_notes" required minlength="10" maxlength="2000" rows="5" class="mt-2 w-full rounded-xl border-slate-300 text-sm" placeholder="Explain why this claim is being rejected.">{{ old('admin_notes') }}</textarea>
            @error('admin_notes')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('rejectDialog').close()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white">Confirm rejection</button>
            </div>
        </form>
    </dialog>
@endif

<script>
function confirmReview(form, message) {
    if (!window.confirm(message)) return false;
    return submitReview(form);
}
function submitReview(form) {
    const button = form.querySelector('button[type="submit"]');
    if (button.disabled) return false;
    button.disabled = true;
    button.textContent = 'Submitting…';
    return true;
}
</script>
@if ($assignment && ($assignment->clock_in_lat !== null || $assignment->clock_out_lat !== null || $assignment->last_lat !== null))
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    const points = @js([
        ['Clock-in', $assignment->clock_in_lat, $assignment->clock_in_lng],
        ['Clock-out', $assignment->clock_out_lat, $assignment->clock_out_lng],
        ['Last known', $assignment->last_lat, $assignment->last_lng],
    ]);
    const validPoints = points.filter(point => point[1] !== null && point[2] !== null);
    if (validPoints.length) {
        const map = L.map('assignmentMap').setView([validPoints[0][1], validPoints[0][2]], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
        const markers = validPoints.map(point => L.marker([point[1], point[2]]).addTo(map).bindPopup(point[0]));
        if (markers.length > 1) map.fitBounds(L.featureGroup(markers).getBounds().pad(0.2));
    }
    </script>
@endif
</body>
</html>
