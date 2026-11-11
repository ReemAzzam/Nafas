<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WellnessPlanResource;
use App\Models\Goal;
use App\Models\SupportMethod;
use App\Services\CurrentPlanService;
use App\Services\PlanGeneratorService;
use Illuminate\Http\Request;
use RuntimeException;

class OnboardingController extends Controller
{
    public function __construct(
        private PlanGeneratorService $planService,
        private CurrentPlanService $currentPlanService,
    ) {}

    public function goals()
    {
        return response()->json([
            'success' => true,
            'data' => Goal::query()->orderBy('id')->get(['id', 'title', 'icon']),
        ]);
    }

    public function supportMethods()
    {
        return response()->json([
            'success' => true,
            'data' => SupportMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'title', 'subtitle', 'icon']),
        ]);
    }

    public function saveGoals(Request $request)
    {
        $validated = $request->validate([
            'goal_ids' => 'required|array|min:1|max:3',
            'goal_ids.*' => 'integer|distinct|exists:goals,id',
        ]);

        $user = $request->user();
        $user->goals()->sync($validated['goal_ids']);
        $user->update(['onboarding_status' => 'goals_set']);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ الأهداف بنجاح.',
            'onboarding_status' => $user->fresh()->onboarding_status,
        ]);
    }

    public function skip(Request $request)
    {
        $user = $request->user();

        $user->goals()->sync([]);
        $user->supportMethods()->sync([]);
        $user->update(['onboarding_status' => 'skipped']);

        return response()->json([
            'success' => true,
            'message' => 'يمكنك إنشاء خطتك في أي وقت لاحقًا.',
            'onboarding_status' => 'skipped',
            'plan' => null,
        ]);
    }

    public function saveSupportMethods(Request $request)
    {
        $validated = $request->validate([
            'method_ids' => 'required|array|min:1',
            'method_ids.*' => 'integer|distinct|exists:support_methods,id',
        ]);

        $user = $request->user();
        $user->supportMethods()->sync($validated['method_ids']);
        $user->update(['onboarding_status' => 'assessed']);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ أسلوب الدعم بنجاح.',
            'onboarding_status' => $user->fresh()->onboarding_status,
        ]);
    }

    public function generatePlan(Request $request)
    {
        $user = $request->user();

        if (! in_array($user->onboarding_status, ['assessed', 'plan_ready'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'يجب اختيار الأهداف وأسلوب الدعم أولاً.',
            ], 403);
        }

        try {
            $plan = $this->planService->generateWeeklyPlan($user);
        } catch (RuntimeException $e) {
            $message = $e->getMessage() === 'NO_CONTENT'
                ? 'لا يوجد محتوى كافٍ لتوليد الخطة حالياً.'
                : 'يجب اختيار الأهداف أولاً.';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم توليد الخطة بنجاح.',
            'onboarding_status' => $user->fresh()->onboarding_status,
            'plan' => WellnessPlanResource::make($plan),
        ], 201);
    }

    public function getCurrentPlan(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'onboarding_status' => $user->onboarding_status,
            'plan' => $this->currentPlanService->get($user),
        ]);
    }
}
