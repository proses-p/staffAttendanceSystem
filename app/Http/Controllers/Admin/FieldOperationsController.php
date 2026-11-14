<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FieldOperationsController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $activeVisits = FieldAssignment::query()
            ->where('company_id', $companyId)
            ->whereNull('clock_out_at')
            ->with('user:id,name')
            ->orderBy('clock_in_at')
            ->get();
        $completedVisits = FieldAssignment::query()
            ->where('company_id', $companyId)
            ->whereNotNull('clock_out_at')
            ->with(['user:id,name', 'expenses:id,field_assignment_id,amount,description,receipt_image_path'])
            ->latest('clock_out_at')
            ->limit(100)
            ->get()
            ->map(function (FieldAssignment $assignment): array {
                $expense = $assignment->expenses->sortByDesc('id')->first();
                $receiptUrl = null;
                if ($expense?->receipt_image_path) {
                    $receiptUrl = Storage::disk('public')->exists($expense->receipt_image_path)
                        ? url(Storage::disk('public')->url($expense->receipt_image_path))
                        : route('admin.field-expenses.receipt', $expense);
                }

                return [
                    'assignment' => $assignment,
                    'expense_amount' => (float) ($expense?->amount ?? 0),
                    'expense_description' => $expense?->description,
                    'receipt_url' => $receiptUrl,
                    'duration_minutes' => $assignment->clock_in_at && $assignment->clock_out_at
                        ? max(0, (int) floor(
                            $assignment->clock_in_at->diffInSeconds(
                                $assignment->clock_out_at
                            ) / 60
                        ))
                        : 0,
                ];
            });

        return view('admin.field-operations.index', compact(
            'activeVisits',
            'completedVisits'
        ));
    }
}
