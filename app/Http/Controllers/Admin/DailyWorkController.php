<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyWorkSubmission;
use App\Models\StaffAssignedTask;
use App\Models\StaffNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DailyWorkController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403);

        DailyWorkSubmission::query()
            ->where('company_id', $companyId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $staff = User::query()
            ->where('company_id', $companyId)
            ->where('role', 'staff')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $submissions = DailyWorkSubmission::query()
            ->where('company_id', $companyId)
            ->with(['staff:id,name,email', 'assignedTask:id,title', 'photos'])
            ->when(
                ! empty($filters['user_id']),
                fn ($query) => $query->where('user_id', $filters['user_id'])
            )
            ->when(
                ! empty($filters['date']),
                fn ($query) => $query->whereDate('submission_date', $filters['date'])
            )
            ->orderByDesc('submission_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.daily-work.index', compact('submissions', 'staff', 'filters'));
    }

    public function assignTask(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId, 403);
        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('company_id', $companyId)
                    ->where('role', 'staff'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'due_date' => ['nullable', 'date'],
        ]);

        StaffAssignedTask::create([
            ...$data,
            'company_id' => $companyId,
            'assigned_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.daily-work.index')
            ->with('success', 'Task assigned to staff.');
    }

    public function accept(Request $request, DailyWorkSubmission $submission): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId && (int) $submission->company_id === (int) $companyId, 404);
        $submission->update([
            'review_status' => 'accepted',
            'admin_feedback' => null,
        ]);

        StaffNotification::create([
            'company_id' => $companyId,
            'user_id' => $submission->user_id,
            'title' => 'Daily work report accepted',
            'body' => 'Your daily work report for '.$submission->submission_date->format('d M Y').' has been accepted.',
            'is_pushed' => false,
        ]);

        return back()->with('success', 'Daily work report accepted.');
    }

    public function requestRevision(Request $request, DailyWorkSubmission $submission): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        abort_unless($companyId && (int) $submission->company_id === (int) $companyId, 404);
        $data = $request->validate([
            'admin_feedback' => ['required', 'string', 'max:5000'],
        ]);
        if ($submission->review_status === 'revision_requested') {
            throw ValidationException::withMessages([
                'review_status' => 'A revision has already been requested for this submission.',
            ]);
        }

        $submission->update([
            'review_status' => 'revision_requested',
            'admin_feedback' => $data['admin_feedback'],
        ]);

        StaffNotification::create([
            'company_id' => $companyId,
            'user_id' => $submission->user_id,
            'title' => 'Daily work report revision requested',
            'body' => 'Please revise your daily work report for '.$submission->submission_date->format('d M Y').'. '.$data['admin_feedback'],
            'is_pushed' => false,
        ]);

        return back()->with('success', 'Revision requested and staff notified.');
    }
}
