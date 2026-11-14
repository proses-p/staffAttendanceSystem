<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyChatMessage;
use App\Models\PrivateChatMessage;
use App\Models\StaffNotification;
use App\Models\User;
use Illuminate\Http\Request;

class ChatApiController extends Controller
{
    public function group(Request $request)
    {
        $user = $request->user();
        $afterId = $request->query('after_id');
        $messageQuery = CompanyChatMessage::query()
            ->where('company_id', $user->company_id)
            ->with('user:id,name,role');
        if ($afterId === null) {
            $messageQuery->orderByDesc('id')->limit(100);
        } else {
            $messageQuery->where('id', '>', (int) $afterId)->orderBy('id')->limit(200);
        }
        $messages = $messageQuery->get();
        if ($afterId === null) {
            $messages = $messages->reverse()->values();
        }
        $messages = $messages->map(
            fn (CompanyChatMessage $message): array => $this->groupMessagePayload($message)
        );

        return response()->json([
            'messages' => $messages,
            'unread_private_count' => PrivateChatMessage::query()
                ->where('company_id', $user->company_id)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function sendGroup(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $message = CompanyChatMessage::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ])->load('user:id,name,role');

        return response()->json(['message' => $this->groupMessagePayload($message)], 201);
    }

    public function updateGroup(Request $request, int $id)
    {
        $message = CompanyChatMessage::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($id)
            ->firstOrFail();
        abort_unless($message->user_id === $request->user()->id, 403);

        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $message->forceFill(['message' => $data['message'], 'edited_at' => now()])->save();
        $message->load('user:id,name,role');

        return response()->json(['message' => $this->groupMessagePayload($message)]);
    }

    public function destroyGroup(Request $request, int $id)
    {
        $message = CompanyChatMessage::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($id)
            ->firstOrFail();
        abort_unless(
            $message->user_id === $request->user()->id ||
            $request->user()->role === 'admin',
            403
        );
        $message->delete();

        return response()->json(['message' => 'Message deleted.']);
    }

    public function conversations(Request $request)
    {
        $user = $request->user();
        $colleagues = User::query()
            ->where('company_id', $user->company_id)
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(function (User $colleague) use ($user): array {
                $conversation = $this->conversationQuery(
                    (int) $user->company_id,
                    (int) $user->id,
                    (int) $colleague->id
                );
                $lastMessage = (clone $conversation)->with(['sender:id,name,role'])
                    ->orderByDesc('id')
                    ->first();

                return [
                    'user' => $colleague,
                    'last_message' => $lastMessage?->message,
                    'last_message_at' => $lastMessage?->created_at,
                    'last_message_sender_id' => $lastMessage?->sender_id,
                    'unread_count' => PrivateChatMessage::query()
                        ->where('company_id', $user->company_id)
                        ->where('sender_id', $colleague->id)
                        ->where('receiver_id', $user->id)
                        ->where('is_read', false)
                        ->count(),
                ];
            });

        return response()->json(['conversations' => $colleagues]);
    }

    public function privateHistory(Request $request, int $colleagueId)
    {
        $user = $request->user();
        $colleague = $this->colleague($user, $colleagueId);

        PrivateChatMessage::query()
            ->where('company_id', $user->company_id)
            ->where('sender_id', $colleague->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = $this->conversationQuery(
            (int) $user->company_id,
            (int) $user->id,
            (int) $colleague->id
        )->with(['sender:id,name,role', 'receiver:id,name,role'])
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $messages = $messages->reverse()->values();

        return response()->json([
            'colleague' => $colleague->only(['id', 'name', 'role']),
            'messages' => $messages,
        ]);
    }

    public function sendPrivate(Request $request, int $colleagueId)
    {
        $user = $request->user();
        $colleague = $this->colleague($user, $colleagueId);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'quoted_message' => ['nullable', 'string', 'max:2000'],
        ]);
        $message = PrivateChatMessage::create([
            'company_id' => $user->company_id,
            'sender_id' => $user->id,
            'receiver_id' => $colleague->id,
            'quoted_message' => $data['quoted_message'] ?? null,
            'message' => $data['message'],
        ])->load(['sender:id,name,role', 'receiver:id,name,role']);

        StaffNotification::create([
            'company_id' => $user->company_id,
            'user_id' => $colleague->id,
            'title' => 'New private message',
            'body' => mb_substr($user->name . ': ' . $message->message, 0, 1000),
        ]);

        return response()->json(['message' => $message], 201);
    }

    public function updatePrivate(Request $request, int $id)
    {
        $message = PrivateChatMessage::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($id)
            ->firstOrFail();
        abort_unless($message->sender_id === $request->user()->id, 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $message->forceFill(['message' => $data['message'], 'edited_at' => now()])->save();

        return response()->json(['message' => $message->load('sender:id,name,role')]);
    }

    public function destroyPrivate(Request $request, int $id)
    {
        $message = PrivateChatMessage::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($id)
            ->firstOrFail();
        abort_unless($message->sender_id === $request->user()->id, 403);
        $message->delete();

        return response()->json(['message' => 'Message deleted.']);
    }

    private function colleague(User $user, int $colleagueId): User
    {
        abort_if($user->id === $colleagueId, 422, 'You cannot message yourself.');

        return User::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->whereKey($colleagueId)
            ->firstOrFail();
    }

    private function conversationQuery(int $companyId, int $userId, int $colleagueId)
    {
        return PrivateChatMessage::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($userId, $colleagueId): void {
                $query->where(fn ($pair) => $pair->where('sender_id', $userId)
                    ->where('receiver_id', $colleagueId))
                    ->orWhere(fn ($pair) => $pair->where('sender_id', $colleagueId)
                        ->where('receiver_id', $userId));
            });
    }

    private function groupMessagePayload(CompanyChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'user' => $message->user?->only(['id', 'name', 'role']),
            'message' => $message->message,
            'edited_at' => $message->edited_at,
            'created_at' => $message->created_at,
        ];
    }
}
