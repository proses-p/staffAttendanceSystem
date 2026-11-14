<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="15">
    <title>Team Chat · StaffFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/staffflow.css') }}">
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
    <div class="min-h-screen flex flex-col lg:flex-row">
        @include('components.admin-sidebar')
        <main class="min-w-0 flex-1 scroll-mt-0 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl">
                <header class="mb-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600">Organization</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">Team Chat</h1>
                    <p class="mt-2 text-sm text-slate-500">Organization announcements and private staff messages.</p>
                </header>

                @if (session('status'))
                    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <nav class="mb-4 flex gap-2">
                    <a href="{{ route('admin.chat.index', ['tab' => 'group']) }}" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $activeTab === 'group' ? 'bg-cyan-700 text-white' : 'bg-white text-slate-700' }}">Group Chat</a>
                    <a href="{{ route('admin.chat.index', ['tab' => 'private']) }}" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $activeTab === 'private' ? 'bg-cyan-700 text-white' : 'bg-white text-slate-700' }}">
                        Private Messages
                        @if ($unreadPrivateCount > 0)<span class="ml-1 rounded-full bg-rose-600 px-2 py-0.5 text-xs text-white">{{ $unreadPrivateCount }}</span>@endif
                    </a>
                </nav>

                @if ($activeTab === 'group')
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 max-h-[60vh] space-y-3 overflow-y-auto">
                            @forelse ($messages as $message)
                                <article class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-semibold">{{ $message->user?->name ?? 'Former user' }}
                                                <span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] uppercase">{{ $message->user?->role ?? 'user' }}</span>
                                            </div>
                                            <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ $message->message }}</p>
                                            <p class="mt-2 text-xs text-slate-400">{{ $message->created_at->format('d M Y, H:i') }} @if ($message->edited_at) · edited @endif</p>
                                        </div>
                                        <div class="flex shrink-0 gap-2">
                                            @if ($message->user_id === auth()->id())
                                                <form method="POST" action="{{ route('admin.chat.group.update', $message) }}" class="flex gap-1">
                                                    @csrf @method('PUT')
                                                    <input name="message" value="{{ $message->message }}" maxlength="2000" required class="w-36 rounded-lg border border-slate-300 px-2 py-1 text-xs">
                                                    <button class="rounded-lg bg-cyan-700 px-2 py-1 text-xs font-semibold text-white">Edit</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.chat.group.destroy', $message) }}" onsubmit="return confirm('Delete this group message?')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <p class="py-10 text-center text-sm text-slate-500">No messages yet. Start the conversation.</p>
                            @endforelse
                        </div>
                        <form method="POST" action="{{ route('admin.chat.group.store') }}" class="flex gap-2">
                            @csrf
                            <textarea name="message" rows="2" maxlength="2000" required placeholder="Write an organization message…" class="min-w-0 flex-1 rounded-xl border border-slate-300 p-3 text-sm"></textarea>
                            <button class="self-end rounded-xl bg-cyan-700 px-5 py-3 text-sm font-semibold text-white">Send</button>
                        </form>
                    </section>
                @else
                    <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
                        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                            <h2 class="px-3 py-2 text-sm font-semibold">Company colleagues</h2>
                            <div class="max-h-[65vh] overflow-y-auto">
                                @foreach ($colleagues as $colleague)
                                    @php
                                        $unread = \App\Models\PrivateChatMessage::query()
                                            ->where('company_id', auth()->user()->company_id)
                                            ->where('sender_id', $colleague->id)
                                            ->where('receiver_id', auth()->id())
                                            ->where('is_read', false)
                                            ->count();
                                    @endphp
                                    <a href="{{ route('admin.chat.index', ['tab' => 'private', 'colleague_id' => $colleague->id]) }}" class="flex items-center justify-between rounded-xl px-3 py-3 text-sm {{ $selectedColleague?->id === $colleague->id ? 'bg-cyan-50 text-cyan-800' : 'hover:bg-slate-50' }}">
                                        <span><span class="block font-medium">{{ $colleague->name }}</span><span class="text-xs text-slate-500">{{ ucfirst($colleague->role) }}</span></span>
                                        @if ($unread > 0)<span class="rounded-full bg-rose-600 px-2 py-0.5 text-xs font-bold text-white">{{ $unread }}</span>@endif
                                    </a>
                                @endforeach
                            </div>
                        </section>
                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            @if ($selectedColleague)
                                <h2 class="mb-4 font-semibold">Private conversation with {{ $selectedColleague->name }}</h2>
                                <div class="mb-4 max-h-[52vh] space-y-3 overflow-y-auto">
                                    @forelse ($privateMessages as $message)
                                        <article class="max-w-[85%] rounded-xl p-3 {{ $message->sender_id === auth()->id() ? 'ml-auto bg-cyan-50' : 'bg-slate-100' }}">
                                            @if ($message->quoted_message)<blockquote class="mb-2 border-l-2 border-cyan-500 pl-2 text-xs text-slate-500">{{ $message->quoted_message }}</blockquote>@endif
                                            <p class="whitespace-pre-wrap text-sm">{{ $message->message }}</p>
                                            <p class="mt-2 text-right text-[11px] text-slate-400">{{ $message->created_at->format('d M Y, H:i') }} @if ($message->edited_at) · edited @endif</p>
                                        </article>
                                    @empty
                                        <p class="py-10 text-center text-sm text-slate-500">No private messages yet.</p>
                                    @endforelse
                                </div>
                                <form method="POST" action="{{ route('admin.chat.private.store', $selectedColleague) }}" class="flex gap-2">
                                    @csrf
                                    <textarea name="message" rows="2" maxlength="2000" required placeholder="Write a private reply…" class="min-w-0 flex-1 rounded-xl border border-slate-300 p-3 text-sm"></textarea>
                                    <button class="self-end rounded-xl bg-cyan-700 px-5 py-3 text-sm font-semibold text-white">Reply</button>
                                </form>
                            @else
                                <p class="py-16 text-center text-sm text-slate-500">Select a colleague to view and reply to their private messages.</p>
                            @endif
                        </section>
                    </div>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
