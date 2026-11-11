<?php

namespace App\Services;

use App\Models\Article;
use App\Models\AudioTrack;
use App\Models\Exercise;
use App\Models\Factor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ProgressInsightService
{
    public const TIMEZONE = 'Asia/Damascus';

    public function build(User $user, string $period, ?string $date = null): array
    {
        [$start, $end] = $this->periodBounds($period, $date);
        $utcStart = $start->utc();
        $utcEnd = $end->endOfDay()->utc();

        $moodLogs = $user->moodLogs()
            ->whereBetween('created_at', [$utcStart, $utcEnd])
            ->get(['id', 'mood_score', 'created_at']);

        $completions = $user->activityCompletions()
            ->whereBetween('created_at', [$utcStart, $utcEnd])
            ->get(['activity_type', 'created_at']);

        $journals = $user->journalEntries()
            ->whereBetween('created_at', [$utcStart, $utcEnd])
            ->get(['created_at']);

        $exerciseCount = $completions->where('activity_type', Exercise::class)->count();
        $articleCount = $completions->where('activity_type', Article::class)->count();
        $audioTrackCount = $completions->where('activity_type', AudioTrack::class)->count();

        return [
            'period' => [
                'type' => $period,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'timezone' => self::TIMEZONE,
            ],
            'summary' => [
                'mood_logs_count' => $moodLogs->count(),
                'average_mood_score' => $moodLogs->isEmpty()
                    ? null
                    : round((float) $moodLogs->avg('mood_score'), 1),
                'completed_activities_count' => $exerciseCount + $articleCount + $audioTrackCount,
                'completed_exercises_count' => $exerciseCount,
                'completed_articles_count' => $articleCount,
                'completed_audio_tracks_count' => $audioTrackCount,
                'journal_entries_count' => $journals->count(),
                'active_days_count' => $this->activeDays($moodLogs, $completions, $journals),
            ],
            'mood_chart' => $this->moodChart($moodLogs, $start, $end),
            'top_factors' => $this->topFactors($user, $utcStart, $utcEnd),
        ];
    }

    private function periodBounds(string $period, ?string $date): array
    {
        $anchor = $date
            ? CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE)
            : CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        if ($period === 'month') {
            return [$anchor->startOfMonth(), $anchor->endOfMonth()];
        }

        return [
            $anchor->startOfWeek(CarbonInterface::SATURDAY),
            $anchor->endOfWeek(CarbonInterface::FRIDAY),
        ];
    }

    private function moodChart(Collection $moodLogs, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $grouped = $moodLogs->groupBy(
            fn ($log) => $log->created_at->setTimezone(self::TIMEZONE)->toDateString()
        );

        return collect(CarbonPeriod::create($start, '1 day', $end))
            ->map(function ($date) use ($grouped) {
                $key = $date->format('Y-m-d');
                $logs = $grouped->get($key, collect());

                return [
                    'date' => $key,
                    'average_score' => $logs->isEmpty()
                        ? null
                        : round((float) $logs->avg('mood_score'), 1),
                    'logs_count' => $logs->count(),
                ];
            })
            ->all();
    }

    private function activeDays(Collection ...$collections): int
    {
        return collect($collections)
            ->flatten(1)
            ->map(fn ($item) => $item->created_at->setTimezone(self::TIMEZONE)->toDateString())
            ->unique()
            ->count();
    }

    private function topFactors(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return Factor::query()
            ->select(['factors.id', 'factors.slug', 'factors.title', 'factors.icon'])
            ->selectRaw('COUNT(mood_log_factor.factor_id) as occurrences_count')
            ->join('mood_log_factor', 'mood_log_factor.factor_id', '=', 'factors.id')
            ->join('mood_logs', 'mood_logs.id', '=', 'mood_log_factor.mood_log_id')
            ->where('mood_logs.user_id', $user->id)
            ->whereBetween('mood_logs.created_at', [$start, $end])
            ->groupBy('factors.id', 'factors.slug', 'factors.title', 'factors.icon')
            ->orderByDesc('occurrences_count')
            ->orderBy('factors.id')
            ->limit(3)
            ->get()
            ->map(fn ($factor) => [
                'id' => $factor->id,
                'slug' => $factor->slug,
                'title' => $factor->title,
                'icon' => $factor->icon,
                'count' => (int) $factor->occurrences_count,
            ])
            ->all();
    }
}
