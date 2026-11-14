<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementRead;
use App\Models\CompanyAnnouncement;
use Illuminate\Http\Request;

class AnnouncementApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();
        $query = CompanyAnnouncement::query()
            ->where('company_id', $user->company_id)
            ->where(function ($query) use ($today): void {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            });
        $announcements = (clone $query)
            ->with('author:id,name')
            ->withExists([
                'reads as is_read' => fn ($reads) => $reads->where('user_id', $user->id),
            ])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CompanyAnnouncement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'priority' => $announcement->priority,
                'is_pinned' => $announcement->is_pinned,
                'expires_at' => $announcement->expires_at?->toDateString(),
                'created_at' => $announcement->created_at?->toISOString(),
                'author_name' => $announcement->author?->name,
                'is_read' => (bool) $announcement->is_read,
            ]);
        $unreadCount = (clone $query)
            ->whereDoesntHave('reads', fn ($reads) => $reads->where('user_id', $user->id))
            ->count();

        return response()->json([
            'announcements' => $announcements,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        $user = $request->user();
        $announcement = CompanyAnnouncement::query()
            ->where('company_id', $user->company_id)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->whereKey($id)
            ->firstOrFail();

        $read = AnnouncementRead::query()->firstOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
            ],
            ['read_at' => now()]
        );

        return response()->json([
            'success' => true,
            'is_read' => true,
            'read_at' => $read->read_at?->toISOString(),
        ]);
    }
}
