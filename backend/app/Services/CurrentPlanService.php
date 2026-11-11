<?php

namespace App\Services;

use App\Http\Resources\PlanDayResource;
use App\Models\Exercise;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CurrentPlanService
{
    public function get(User $user): ?array
    {
        $today = CarbonImmutable::now('Asia/Damascus')->toDateString();

        $activePlan = $user->wellnessPlans()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->with(['days' => fn ($query) => $query
                ->orderBy('day_number')
                ->with([
                    'activities.activity' => function (MorphTo $morphTo): void {
                        $morphTo->morphWith([
                            Exercise::class => ['optionalAudioTrack'],
                        ]);
                    },
                ])])
            ->first();

        if (! $activePlan) {
            return null;
        }

        $days = $activePlan->days;
        $todayPlanDay = $days->first(
            fn ($day) => $day->date->toDateString() === $today
        );
        $completedDays = $days->where('is_completed', true)->count();
        $totalDays = max($days->count(), 1);

        return [
            'id' => $activePlan->id,
            'title' => $activePlan->title,
            'start_date' => $activePlan->start_date->toDateString(),
            'end_date' => $activePlan->end_date->toDateString(),
            'current_day' => $todayPlanDay?->day_number,
            'completed_days' => $completedDays,
            'total_days' => $totalDays,
            'progress_percent' => (int) round(($completedDays / $totalDays) * 100),
            'today' => $todayPlanDay
                ? PlanDayResource::make($todayPlanDay)->resolve()
                : null,
            'days' => PlanDayResource::collection($days)->resolve(),
        ];
    }
}
