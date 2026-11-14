<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyChatMessage;
use App\Models\PrivateChatMessage;
use App\Models\StaffNotification;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();
        $companyId = $admin->company_id;
        $activeTab = $request->query('tab') === 'private' ? 'private' : 'group';
        $colleagues = User::query()
            ->where('company_id', $companyId)
            ->where('id', '!=', $admin->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
        $selectedColleague = $colleagues->firstWhere('id', (int) $request->query('colleague_id'));

        $messages = CompanyChatMessage::query()
            ->where('company_id', $companyId)
            ->with('user:id,name,role')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        $privateMessages = collect();
        if ($selectedColleague) {
            PrivateChatMessage::query()
                ->where('company_id', $companyId)
                ->where('sender_id', $selectedColleague->id)
                ->where('receiver_id', $admin->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
            $privateMessages = PrivateChatMessage::query()
                ->where('company_id', $companyId)
                ->where(fn ($query) => $query
                    ->where(fn ($pair) => $pair->where('sender_id', $admin->id)
                        ->where('receiver_id', $selectedColleague->id))
                    ->orWhere(fn ($pair) => $pair->where('sender_id', $selectedColleague->id)
                        ->where('receiver_id', $admin->id)))
                ->with(['sender:id,name,role', 'receiver:id,name,role'])
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->reverse()
                ->values();
        }

        $unreadPrivateCount = PrivateChatMessage::query()
            ->where('company_id', $companyId)
            ->where('receiver_id', $admin->id)
            ->where('is_read', false)
            ->count();

        return view('admin.chat.index', compact(
            'activeTab',
            'colleagues',
            'messages',
            'privateMessages',
            'selectedColleague',
            'unreadPrivateCount'
        ));
    }

    public function sendGroup(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        CompanyChatMessage::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        return redirect()->route('admin.chat.index', ['tab' => 'group'])
            ->with('status', 'Group message sent.');
    }

    public function updateGroup(Request $request, CompanyChatMessage $message)
    {
        abort_unless($message->company_id === $request->user()->company_id, 404);
        abort_unless($message->user_id === $request->user()->id, 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $message->forceFill(['message' => $data['message'], 'edited_at' => now()])->save();

        return redirect()->route('admin.chat.index', ['tab' => 'group'])
            ->with('status', 'Message updated.');
    }

    public function destroyGroup(Request $request, CompanyChatMessage $message)
    {
        abort_unless($message->company_id === $request->user()->company_id, 404);
        $message->delete();

        return redirect()->route('admin.chat.index', ['tab' => 'group'])
            ->with('status', 'Message deleted.');
    }

    public function sendPrivate(Request $request, int $colleague)
    {
        $admin = $request->user();
        $recipient = User::query()
            ->where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->whereKey($colleague)
            ->firstOrFail();
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        PrivateChatMessage::create([
            'company_id' => $admin->company_id,
            'sender_id' => $admin->id,
            'receiver_id' => $recipient->id,
            'message' => $data['message'],
        ]);
        StaffNotification::create([
            'company_id' => $admin->company_id,
            'user_id' => $recipient->id,
            'title' => 'New private message',
            'body' => mb_substr($admin->name . ': ' . $data['message'], 0, 1000),
        ]);

        return redirect()->route('admin.chat.index', [
            'tab' => 'private',
            'colleague_id' => $recipient->id,
        ])->with('status', 'Private reply sent.');
    }
}
