<?php

namespace App\Services\Admin;

use App\Models\ActivityCompletion;
use App\Models\Article;
use App\Models\ArticleCommentReport;
use App\Models\Exercise;
use App\Models\Feedback;
use App\Models\MoodLog;
use App\Models\SosDailyStat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function overview(): array
    {
        $today = CarbonImmutable::now('Asia/Damascus')->startOfDay();
        $weekStart = $today->subDays(6);
        $firstSevenDaysRetention = $this->firstSevenDaysRetention($today);

        return [
            'users' => [
                'active' => User::query()->where('role', 'user')->where('is_blocked', false)->count(),
                'blocked' => User::query()->where('role', 'user')->where('is_blocked', true)->count(),
                'deleted' => User::onlyTrashed()->where('role', 'user')->count(),
                'new_last_7_days' => User::withTrashed()->where('role', 'user')->where('created_at', '>=', $weekStart->utc())->count(),
                'new_last_30_days' => User::withTrashed()->where('role', 'user')->where('created_at', '>=', $today->subDays(29)->utc())->count(),
            ],
            'engagement' => [
                'mood_logs_total' => MoodLog::query()->count(),
                'active_users_last_7_days' => MoodLog::query()
                    ->where('created_at', '>=', $weekStart->utc())
                    ->distinct('user_id')
                    ->count('user_id'),
                'activity_completions_total' => ActivityCompletion::query()->count(),
                'sos_opens_last_7_days' => (int) SosDailyStat::query()
                    ->whereDate('date', '>=', $weekStart->toDateString())
                    ->sum('opens_count'),
                'first_7_days_retention' => $firstSevenDaysRetention,
            ],
            'review_queue' => [
                'pending_comment_reports' => ArticleCommentReport::query()->where('status', 'pending')->count(),
                'pending_feedback' => Feedback::query()->where('status', 'pending')->count(),
            ],
            'content' => [
                'published_articles' => Article::query()->where('is_published', true)->count(),
                'draft_articles' => Article::query()->where('is_published', false)->count(),
                'published_exercises' => Exercise::query()->where('is_published', true)->count(),
                'draft_exercises' => Exercise::query()->where('is_published', false)->count(),
            ],
            'weekly_activity' => $this->weeklyActivity($weekStart),
        ];
    }

    private function firstSevenDaysRetention(CarbonImmutable $today): array
    {
        // لا يدخل المستخدم في العينة حتى تنتهي أيامه السبعة الأولى كاملة.
        $eligibleBefore = $today->subDays(6)->utc();

        $eligibleUsers = User::withTrashed()
            ->where('role', 'user')
            ->where('created_at', '<', $eligibleBefore)
            ->count();

        $retainedUsers = DB::table('users')
            ->join('mood_logs', 'mood_logs.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->where('users.created_at', '<', $eligibleBefore)
            ->whereRaw(<<<'SQL'
                DATEDIFF(
                    DATE(CONVERT_TZ(mood_logs.created_at, '+00:00', '+03:00')),
                    DATE(CONVERT_TZ(users.created_at, '+00:00', '+03:00'))
                ) BETWEEN 0 AND 6
                SQL)
            ->groupBy('users.id')
            ->havingRaw("COUNT(DISTINCT DATE(CONVERT_TZ(mood_logs.created_at, '+00:00', '+03:00'))) = 7")
            ->get(['users.id'])
            ->count();

        return [
            'rate_percent' => $eligibleUsers === 0
                ? 0.0
                : round(($retainedUsers / $eligibleUsers) * 100, 1),
            'retained_users' => $retainedUsers,
            'eligible_users' => $eligibleUsers,
            'required_consecutive_days' => 7,
        ];
    }

    private function weeklyActivity(CarbonImmutable $start): array
    {
        $moods = MoodLog::query()
            ->where('created_at', '>=', $start->utc())
            ->get(['user_id', 'created_at'])
            ->groupBy(fn (MoodLog $log) => $log->created_at->timezone('Asia/Damascus')->toDateString());

        $sos = SosDailyStat::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->pluck('opens_count', 'date');

        return Collection::times(7, function (int $offset) use ($start, $moods, $sos) {
            $date = $start->addDays($offset - 1)->toDateString();
            $dayLogs = $moods->get($date, collect());

            return [
                'date' => $date,
                'mood_logs' => $dayLogs->count(),
                'active_users' => $dayLogs->pluck('user_id')->unique()->count(),
                'sos_opens' => (int) ($sos[$date] ?? 0),
            ];
        })->all();
    }
}
