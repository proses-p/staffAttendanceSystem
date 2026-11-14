<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AiHrAnalystService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AiAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $month = $data['month'] ?? now()->format('Y-m');
        $staff = User::query()
            ->where('company_id', $request->user()->company_id)
            ->where('role', 'staff')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.ai-analyst.index', compact('month', 'staff'));
    }

    public function analyzeCompany(
        Request $request,
        AiHrAnalystService $analyst
    ): JsonResponse {
        if ($response = $this->ensureAiEnabled($request)) {
            return $response;
        }
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $analysis = $analyst->analyzeCompanyMonthlyPerformance(
                (int) $request->user()->company_id,
                $data['month']
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json(['analysis' => $analysis]);
    }

    public function analyzeStaff(
        Request $request,
        AiHrAnalystService $analyst
    ): JsonResponse {
        if ($response = $this->ensureAiEnabled($request)) {
            return $response;
        }
        $data = $request->validate([
            'staff_id' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m'],
            'custom_question' => ['nullable', 'string', 'max:2000'],
        ]);
        $staff = User::query()
            ->where('company_id', $request->user()->company_id)
            ->where('role', 'staff')
            ->where('is_active', true)
            ->whereKey($data['staff_id'])
            ->firstOrFail();

        try {
            $analysis = $analyst->analyzeIndividualStaffPerformance(
                (int) $request->user()->company_id,
                (int) $staff->id,
                $data['month'],
                $data['custom_question'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json(['analysis' => $analysis]);
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
}
