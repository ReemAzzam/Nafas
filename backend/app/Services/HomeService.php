<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Exercise;
use App\Models\User;
use Carbon\CarbonImmutable;

class HomeService
{
    public function __construct(private CurrentPlanService $currentPlanService) {}

    public function build(User $user): array
    {
        $latestMood = $user->moodLogs()
            ->with(['factors', 'suggestedExercise', 'suggestedArticle'])
            ->latest()
            ->first();

        $featuredArticle = Article::query()
            ->where('is_published', true)
            ->with(['favoriteUsers' => fn ($query) => $query->whereKey($user->id)])
            ->latest('id')
            ->first();

        return [
            'user' => $user,
            'latest_mood' => $latestMood,
            'has_checked_in_today' => $latestMood?->created_at
                ? $latestMood->created_at
                    ->timezone('Asia/Damascus')
                    ->isSameDay(CarbonImmutable::now('Asia/Damascus'))
                : false,
            'current_plan' => $this->currentPlanService->get($user),
            'calming_exercise' => Exercise::query()
                ->where('slug', SosCalmingService::EXERCISE_SLUGS[0])
                ->first(),
            'featured_article' => $featuredArticle,
        ];
    }
}
