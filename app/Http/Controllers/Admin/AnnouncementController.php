<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyAnnouncement;
use App\Models\StaffNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $activeStaffCount = User::query()
            ->where('company_id', $companyId)
            ->where('role', 'staff')
            ->where('is_active', true)
            ->count();
        $announcements = CompanyAnnouncement::query()
            ->where('company_id', $companyId)
            ->with('author:id,name')
            ->withCount([
                'reads as staff_read_count' => fn ($reads) => $reads->whereHas(
                    'user',
                    fn ($users) => $users->where('company_id', $companyId)
                        ->where('role', 'staff')
                        ->where('is_active', true)
                ),
            ])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.announcements.index', compact(
            'announcements',
            'activeStaffCount'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'priority' => ['required', 'in:normal,important,urgent'],
            'is_pinned' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $companyId = (int) $request->user()->company_id;

        DB::transaction(function () use ($data, $companyId, $request): void {
            $announcement = CompanyAnnouncement::query()->create([
                'company_id' => $companyId,
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'content' => $data['content'],
                'priority' => $data['priority'],
                'is_pinned' => $request->boolean('is_pinned'),
                'expires_at' => $data['expires_at'] ?? null,
            ]);
            $staffIds = User::query()
                ->where('company_id', $companyId)
                ->where('role', 'staff')
                ->where('is_active', true)
                ->pluck('id');
            $body = $announcement->content;

            foreach ($staffIds as $staffId) {
                StaffNotification::query()->create([
                    'company_id' => $companyId,
                    'user_id' => $staffId,
                    'title' => $announcement->title,
                    'body' => $body,
                    'is_pushed' => false,
                ]);
            }
        });

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement published and active staff notified.');
    }

    public function togglePin(Request $request, int $announcement): RedirectResponse
    {
        $record = CompanyAnnouncement::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($announcement)
            ->firstOrFail();
        $record->is_pinned = ! $record->is_pinned;
        $record->save();

        return back()->with('success', $record->is_pinned
            ? 'Announcement pinned.'
            : 'Announcement unpinned.');
    }

    public function destroy(Request $request, int $announcement): RedirectResponse
    {
        CompanyAnnouncement::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($announcement)
            ->firstOrFail()
            ->delete();

        return back()->with('success', 'Announcement deleted.');
    }
}
