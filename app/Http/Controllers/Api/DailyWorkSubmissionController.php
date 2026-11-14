<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyWorkSubmission;
use App\Models\StaffAssignedTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DailyWorkSubmissionController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = today();

        $todaySubmission = DailyWorkSubmission::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('submission_date', $today)
            ->with(['photos', 'assignedTask'])
            ->first();

        $history = DailyWorkSubmission::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('submission_date', '<', $today)
            ->with(['photos', 'assignedTask'])
            ->orderByDesc('submission_date')
            ->limit(30)
            ->get();

        $tasks = StaffAssignedTask::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'completed')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'instructions', 'due_date', 'status']);

        return response()->json([
            'today_submission' => $this->submissionPayload($todaySubmission),
            'history' => $history->map(fn (DailyWorkSubmission $submission) => $this->submissionPayload($submission)),
            'active_tasks' => $tasks,
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'completed_part' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'work_status' => ['required', Rule::in(['completed', 'in_progress', 'blocked'])],
            'assigned_task_id' => ['nullable', 'integer'],
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'image', 'max:5120'],
        ]);

        $assignedTask = null;
        if (! empty($data['assigned_task_id'])) {
            $assignedTask = StaffAssignedTask::query()
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->where('status', '!=', 'completed')
                ->find($data['assigned_task_id']);
            if (! $assignedTask) {
                throw ValidationException::withMessages([
                    'assigned_task_id' => 'The selected task is not assigned to this staff account.',
                ]);
            }
        }

        $storedPaths = [];
        try {
            $submission = DB::transaction(function () use (
                $request,
                $user,
                $data,
                $assignedTask,
                &$storedPaths
            ): DailyWorkSubmission {
                $today = today();
                $submission = DailyWorkSubmission::query()
                    ->where('company_id', $user->company_id)
                    ->where('user_id', $user->id)
                    ->whereDate('submission_date', $today)
                    ->lockForUpdate()
                    ->first();

                if ($submission && $submission->review_status !== 'revision_requested') {
                    throw ValidationException::withMessages([
                        'submission' => 'You have already submitted your daily work report.',
                    ]);
                }

                $submission ??= new DailyWorkSubmission([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'submission_date' => $today,
                ]);
                $submission->fill([
                    'assigned_task_id' => $assignedTask?->id,
                    'completed_part' => $data['completed_part'],
                    'description' => $data['description'],
                    'work_status' => $data['work_status'],
                    'review_status' => 'pending_review',
                    'admin_feedback' => null,
                    'is_read' => false,
                ]);
                $submission->save();

                $oldPaths = $submission->exists
                    ? $submission->photos()->pluck('photo_path')->all()
                    : [];
                if ($oldPaths !== []) {
                    $submission->photos()->delete();
                }

                foreach ($request->file('photos', []) as $photo) {
                    $path = $photo->store('daily_work_photos', 'public');
                    if ($path === false) {
                        throw new \RuntimeException('A daily work photo could not be stored.');
                    }
                    $storedPaths[] = $path;
                    $submission->photos()->create(['photo_path' => $path]);
                }

                DB::afterCommit(function () use ($oldPaths): void {
                    if ($oldPaths !== []) {
                        Storage::disk('public')->delete($oldPaths);
                    }
                });

                return $submission->load(['photos', 'assignedTask']);
            });
        } catch (\Throwable $error) {
            if ($storedPaths !== []) {
                Storage::disk('public')->delete($storedPaths);
            }
            throw $error;
        }

        return response()->json([
            'message' => 'Daily work report submitted for review.',
            'submission' => $this->submissionPayload($submission),
        ], 201);
    }

    private function submissionPayload(?DailyWorkSubmission $submission): ?array
    {
        if (! $submission) {
            return null;
        }

        return [
            'id' => $submission->id,
            'submission_date' => $submission->submission_date->toDateString(),
            'completed_part' => $submission->completed_part,
            'description' => $submission->description,
            'work_status' => $submission->work_status,
            'review_status' => $submission->review_status,
            'admin_feedback' => $submission->admin_feedback,
            'assigned_task' => $submission->assignedTask
                ? [
                    'id' => $submission->assignedTask->id,
                    'title' => $submission->assignedTask->title,
                ]
                : null,
            'photos' => $submission->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => $photo->url,
            ]),
        ];
    }
}
