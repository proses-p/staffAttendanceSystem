<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffAssignedTask;
use App\Services\StaffAiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StaffAiAssistantController extends Controller
{
    private const DAILY_LIMIT = 15;

    public function ask(Request $request, StaffAiAssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);
        if ($response = $this->ensureAiEnabled($request)) {
            return $response;
        }
        $quota = $this->consumeQuota((int) $request->user()->id);
        if ($quota instanceof JsonResponse) {
            return $quota;
        }

        try {
            $reply = $assistant->ask($request->user(), $data['message']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'remaining_quota' => $quota,
            ], 503);
        }

        return response()->json([
            'reply' => $reply,
            'remaining_quota' => $quota,
        ]);
    }

    public function draftReport(
        Request $request,
        StaffAiAssistantService $assistant
    ): JsonResponse {
        $data = $request->validate([
            'rough_notes' => ['required', 'string', 'max:500'],
            'assigned_task_title' => ['nullable', 'string', 'max:255'],
        ]);
        if ($response = $this->ensureAiEnabled($request)) {
            return $response;
        }

        $taskTitle = trim($data['assigned_task_title'] ?? '');
        if ($taskTitle !== '') {
            $taskExists = StaffAssignedTask::query()
                ->where('company_id', $request->user()->company_id)
                ->where('user_id', $request->user()->id)
                ->where('status', '!=', 'completed')
                ->where('title', $taskTitle)
                ->exists();
            if (! $taskExists) {
                throw ValidationException::withMessages([
                    'assigned_task_title' => ['The selected task is not active or assigned to this staff account.'],
                ]);
            }
        } else {
            $taskTitle = '';
        }

        $quota = $this->consumeQuota((int) $request->user()->id);
        if ($quota instanceof JsonResponse) {
            return $quota;
        }
        try {
            $draft = $assistant->draftReport(
                $request->user(),
                $data['rough_notes'],
                $taskTitle !== '' ? $taskTitle : null
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'remaining_quota' => $quota,
            ], 503);
        }

        return response()->json($draft + ['remaining_quota' => $quota]);
    }

    private function ensureAiEnabled(Request $request): ?JsonResponse
    {
        if ($request->user()->company?->ai_enabled !== true) {
            return response()->json([
                'message' => "AI Assistant features are not enabled for your organization's subscription plan.",
            ], 403);
        }

        return null;
    }

    private function consumeQuota(int $userId): int|JsonResponse
    {
        $key = 'ai_quota_'.$userId.'_'.now()->format('Y-m-d');
        Cache::add($key, 0, now()->endOfDay());
        $count = Cache::increment($key);
        if ($count === false) {
            return response()->json([
                'message' => 'AI usage quota is temporarily unavailable. Please try again later.',
            ], 503);
        }
        if ($count > self::DAILY_LIMIT) {
            Cache::decrement($key);

            return response()->json([
                'message' => 'You have reached your daily limit of 15 AI requests. Please try again tomorrow.',
                'remaining_quota' => 0,
            ], 429);
        }

        return self::DAILY_LIMIT - (int) $count;
    }
}
