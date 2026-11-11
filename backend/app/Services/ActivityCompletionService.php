<?php

namespace App\Services;

use App\Models\ActivityCompletion;
use App\Models\Article;
use App\Models\Exercise;
use App\Models\MoodLog;
use App\Models\PlanActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivityCompletionService
{
    public function complete(
        User $user,
        Model $activity,
        ?int $planActivityId = null,
        ?int $moodLogId = null
    ): array {
        return DB::transaction(function () use ($user, $activity, $planActivityId, $moodLogId) {
            $source = $planActivityId ? 'plan' : ($moodLogId ? 'mood' : 'explore');

            $planActivity = $this->resolvePlanActivity($user, $activity, $planActivityId);
            $moodLog = $this->resolveMoodLog($user, $activity, $moodLogId);

            if ($planActivity && ! $planActivity->is_completed) {
                $planActivity->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);
                $this->refreshDay($planActivity);

                if ($planActivityId) {
                    $source = 'plan';
                }
            }

            if ($moodLog && ! $moodLog->suggestion_completed_at) {
                $moodLog->update(['suggestion_completed_at' => now()]);
            }

            $attributes = [
                'user_id' => $user->id,
                'activity_type' => $activity::class,
                'activity_id' => $activity->id,
                'source' => $source,
                'plan_activity_id' => $planActivity?->id,
                'mood_log_id' => $moodLog?->id,
            ];

            $completion = match (true) {
                $planActivity !== null => ActivityCompletion::firstOrCreate(
                    ['user_id' => $user->id, 'plan_activity_id' => $planActivity->id],
                    $attributes
                ),
                $moodLog !== null => ActivityCompletion::firstOrCreate(
                    ['user_id' => $user->id, 'mood_log_id' => $moodLog->id],
                    $attributes
                ),
                default => ActivityCompletion::create($attributes),
            };

            return [
                'completion' => $completion,
                'plan_progress' => $this->planProgress($user),
            ];
        });
    }

    private function resolvePlanActivity(User $user, Model $activity, ?int $id): ?PlanActivity
    {
        $query = PlanActivity::query()
            ->where('activity_type', $activity::class)
            ->where('activity_id', $activity->id)
            ->whereHas('planDay.wellnessPlan', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('is_active', true);
            })
            ->lockForUpdate();

        if ($id) {
            $row = (clone $query)->where('id', $id)->first();

            if (! $row) {
                throw ValidationException::withMessages([
                    'plan_activity_id' => 'هذا النشاط غير مرتبط بخطتك.',
                ]);
            }

            return $row;
        }

        return $query->where('is_completed', false)->orderBy('id')->first();
    }

    private function resolveMoodLog(User $user, Model $activity, ?int $id): ?MoodLog
    {
        if (! $activity instanceof Article && ! $activity instanceof Exercise) {
            if ($id) {
                throw ValidationException::withMessages([
                    'mood_log_id' => 'هذا النوع من الأنشطة غير مرتبط باقتراحات المزاج.',
                ]);
            }

            return null;
        }

        $type = $activity instanceof Article ? 'article' : 'exercise';
        $fk = $activity instanceof Article ? 'suggested_article_id' : 'suggested_exercise_id';

        $query = $user->moodLogs()
            ->where('suggestion_type', $type)
            ->where($fk, $activity->id)
            ->lockForUpdate();

        if ($id) {
            $row = (clone $query)->where('id', $id)->first();

            if (! $row) {
                throw ValidationException::withMessages([
                    'mood_log_id' => 'هذا الاقتراح غير مرتبط بحسابك.',
                ]);
            }

            return $row;
        }

        return $query->whereNull('suggestion_completed_at')->latest()->first();
    }

    private function refreshDay(PlanActivity $planActivity): void
    {
        $day = $planActivity->planDay()->with('activities')->first();

        if ($day && $day->activities->every(fn ($a) => $a->is_completed)) {
            $day->update(['is_completed' => true]);
        }
    }

    private function planProgress(User $user): ?array
    {
        $plan = $user->wellnessPlans()
            ->where('is_active', true)
            ->with('days')
            ->first();

        if (! $plan) {
            return null;
        }

        $days = $plan->days;
        $completed = $days->where('is_completed', true)->count();
        $total = max($days->count(), 1);

        return [
            'plan_id' => $plan->id,
            'completed_days' => $completed,
            'total_days' => $total,
            'progress_percent' => (int) round(($completed / $total) * 100),
        ];
    }
}
