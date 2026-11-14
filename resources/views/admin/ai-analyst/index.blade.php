<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Data Analyst · StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.6/dist/purify.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
    <style>
        .ai-markdown h3 { margin: 1.35rem 0 .55rem; color: #0f172a; font-size: 1rem; font-weight: 700; }
        .ai-markdown h3:first-child { margin-top: 0; }
        .ai-markdown p { margin: .45rem 0; line-height: 1.65; }
        .ai-markdown ul { margin: .55rem 0 1rem; list-style: disc; padding-left: 1.35rem; }
        .ai-markdown li { margin: .4rem 0; padding-left: .2rem; line-height: 1.6; }
        .ai-markdown strong { color: #0f172a; font-weight: 700; }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
    <div class="min-h-screen flex flex-col lg:flex-row">
        @include('components.admin-sidebar')
        <main class="min-w-0 flex-1 scroll-mt-0 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <header>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Data-grounded insights</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">AI HR &amp; Operations Analyst</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-500">
                        Analyze company attendance, work reports, field activity, and payroll using this company’s records only.
                    </p>
                </header>

                <section class="rounded-2xl border border-cyan-100 bg-cyan-50 p-4 text-sm text-cyan-950">
                    <strong>Data-grounded analysis:</strong> answers are generated from the selected period’s company records. If records are insufficient, the analyst will say so rather than estimate.
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="mb-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700">Company-wide</p>
                        <h2 class="mt-1 text-xl font-semibold">Company Monthly Financial &amp; Risk Analysis</h2>
                        <p class="mt-1 text-sm text-slate-500">Review budget variance, payroll, attendance, work-report completion, and weighted risk factors.</p>
                    </div>
                    <form id="company-form" data-endpoint="{{ route('admin.ai-analyst.company') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label class="text-sm font-medium text-slate-700">
                            Analysis month
                            <input type="month" name="month" value="{{ $month }}" required class="mt-1 block rounded-xl border border-slate-300 px-3 py-2.5">
                        </label>
                        <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-semibold text-white hover:bg-cyan-800 disabled:cursor-wait disabled:opacity-60">
                            Generate Company AI Report
                        </button>
                    </form>
                    <div id="company-status" class="mt-4 hidden rounded-lg p-3 text-sm" role="status"></div>
                    <article id="company-result" class="ai-markdown mt-4 hidden rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700"></article>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="mb-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-orange-700">Individual view</p>
                        <h2 class="mt-1 text-xl font-semibold">Individual Staff Performance &amp; Work Report Analyst</h2>
                        <p class="mt-1 text-sm text-slate-500">Analyze one staff member’s verified monthly records or ask a specific question grounded in their data.</p>
                    </div>
                    <form id="staff-form" data-endpoint="{{ route('admin.ai-analyst.staff') }}" class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium text-slate-700">
                            Staff member
                            <select name="staff_id" required class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5">
                                <option value="">Select a staff member</option>
                                @foreach ($staff as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }} · {{ $member->email }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium text-slate-700">
                            Analysis month
                            <input type="month" name="month" value="{{ $month }}" required class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        </label>
                        <label class="text-sm font-medium text-slate-700 sm:col-span-2">
                            Specific Data Question <span class="font-normal text-slate-400">(optional)</span>
                            <textarea name="custom_question" rows="3" maxlength="2000" placeholder="For example: Which recorded work reports were marked incomplete this month?" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5"></textarea>
                        </label>
                        <div class="sm:col-span-2">
                            <button type="submit" class="rounded-xl bg-orange-500 px-5 py-3 text-sm font-semibold text-white hover:bg-orange-600 disabled:cursor-wait disabled:opacity-60">
                                Analyze Staff Data
                            </button>
                        </div>
                    </form>
                    <div id="staff-status" class="mt-4 hidden rounded-lg p-3 text-sm" role="status"></div>
                    <article id="staff-result" class="ai-markdown mt-4 hidden rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700"></article>
                </section>
            </div>
        </main>
    </div>
    <script>
        marked.setOptions({ gfm: true, breaks: false });

        async function runAnalysis(form, key) {
            const button = form.querySelector('button[type="submit"]');
            const status = document.getElementById(`${key}-status`);
            const result = document.getElementById(`${key}-result`);
            const formData = new FormData(form);
            button.disabled = true;
            button.textContent = 'Analyzing verified records…';
            status.className = 'mt-4 rounded-lg bg-cyan-50 p-3 text-sm text-cyan-800';
            status.textContent = 'Preparing company records for analysis…';
            status.classList.remove('hidden');
            result.classList.add('hidden');

            try {
                const response = await fetch(form.dataset.endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(Object.fromEntries(formData.entries()))
                });
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload.message || 'The analysis request could not be completed.');
                }
                result.innerHTML = DOMPurify.sanitize(marked.parse(payload.analysis || ''));
                result.classList.remove('hidden');
                status.className = 'mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800';
                status.textContent = 'Analysis complete.';
            } catch (error) {
                status.className = 'mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-800';
                status.textContent = error.message || 'Unable to complete the analysis.';
            } finally {
                button.disabled = false;
                button.textContent = key === 'company'
                    ? 'Generate Company AI Report'
                    : 'Analyze Staff Data';
            }
        }

        document.getElementById('company-form').addEventListener('submit', event => {
            event.preventDefault();
            runAnalysis(event.currentTarget, 'company');
        });
        document.getElementById('staff-form').addEventListener('submit', event => {
            event.preventDefault();
            runAnalysis(event.currentTarget, 'staff');
        });
    </script>
</body>
</html>
