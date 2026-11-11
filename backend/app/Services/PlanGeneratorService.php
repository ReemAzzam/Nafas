<?php

namespace App\Services;

use App\Models\Article;
use App\Models\AudioTrack;
use App\Models\Exercise;
use App\Models\PlanActivity;
use App\Models\PlanDay;
use App\Models\User;
use App\Models\WellnessPlan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlanGeneratorService
{
    public function generateWeeklyPlan(User $user): WellnessPlan
    {
        return DB::transaction(function () use ($user) {
            $user = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $today = Carbon::today('Asia/Damascus');

            $goalIds = $user->goals()->pluck('goals.id');

            if ($goalIds->isEmpty()) {
                throw new RuntimeException('NO_GOALS');
            }

            $methodSlugs = $user->supportMethods()->pluck('support_methods.slug');

            $wantExercises = $methodSlugs->isEmpty()
                || $methodSlugs->intersect(['exercises', 'writing', 'listening', 'practical'])->isNotEmpty();

            $wantArticles = $methodSlugs->isEmpty()
                || $methodSlugs->contains('reading');

            $exercises = $wantExercises
                ? $this->resolveExercises($goalIds, $methodSlugs)
                : collect();

            $articles = $wantArticles
                ? $this->resolveArticles($goalIds)
                : collect();

            $audioTracks = $methodSlugs->contains('listening')
                ? $this->resolveAudioTracks()
                : collect();

            if ($exercises->isEmpty() && $articles->isEmpty()) {
                throw new RuntimeException('NO_CONTENT');
            }

            $user->wellnessPlans()
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $plan = WellnessPlan::create([
                'user_id' => $user->id,
                'title' => 'خطتك الأسبوعية المخصصة',
                'description' => 'بداية لطيفة لسبعة أيام، بنشاط واحد مناسب لكل يوم.',
                'start_date' => $today,
                'end_date' => $today->copy()->addDays(6),
                'is_active' => true,
            ]);

            $sequence = $this->buildDailySequence($exercises, $articles, $audioTracks);

            for ($dayNumber = 1; $dayNumber <= 7; $dayNumber++) {
                $planDay = PlanDay::create([
                    'wellness_plan_id' => $plan->id,
                    'day_number' => $dayNumber,
                    'date' => $today->copy()->addDays($dayNumber - 1),
                ]);

                $item = $sequence[$dayNumber - 1];

                PlanActivity::create([
                    'plan_day_id' => $planDay->id,
                    'activity_type' => $item['type'],
                    'activity_id' => $item['id'],
                ]);
            }

            $user->update(['onboarding_status' => 'plan_ready']);

            return $plan->load([
                'days.activities.activity' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Exercise::class => ['optionalAudioTrack'],
                    ]);
                },
            ]);
        });
    }

    private function resolveExercises(Collection $goalIds, Collection $methodSlugs): Collection
    {
        $types = $this->exerciseTypesFor($methodSlugs);

        $query = Exercise::query()
            ->where('is_published', true)
            ->whereIn('type', $types);

        $matched = (clone $query)
            ->whereHas('goals', fn ($q) => $q->whereIn('goals.id', $goalIds))
            ->inRandomOrder()
            ->get();

        if ($matched->isNotEmpty()) {
            return $matched;
        }

        $byType = $query->inRandomOrder()->get();

        return $byType->isNotEmpty()
            ? $byType
            : Exercise::where('is_published', true)->inRandomOrder()->get();
    }

    private function resolveArticles(Collection $goalIds): Collection
    {
        $matched = Article::query()
            ->where('is_published', true)
            ->whereHas('goals', fn ($q) => $q->whereIn('goals.id', $goalIds))
            ->inRandomOrder()
            ->get();

        if ($matched->isNotEmpty()) {
            return $matched;
        }

        return Article::where('is_published', true)->inRandomOrder()->get();
    }

    private function exerciseTypesFor(Collection $methodSlugs): array
    {
        if ($methodSlugs->isEmpty()) {
            return ['breathing', 'grounding', 'journaling', 'relaxation'];
        }

        $types = [];

        if ($methodSlugs->contains('exercises') || $methodSlugs->contains('listening')) {
            $types = array_merge($types, ['breathing', 'grounding', 'relaxation']);
        }

        if ($methodSlugs->contains('writing')) {
            $types[] = 'journaling';
        }

        if ($methodSlugs->contains('practical')) {
            $types = array_merge($types, ['breathing', 'grounding', 'relaxation']);
        }

        $types = array_values(array_unique($types));

        return $types ?: ['breathing', 'grounding', 'journaling', 'relaxation'];
    }

    private function resolveAudioTracks(): Collection
    {
        return AudioTrack::query()
            ->where('is_published', true)
            ->inRandomOrder()
            ->limit(2)
            ->sharedLock()
            ->get();
    }

    /**
     * نشاط واحد لكل يوم (شاشة 09). تناوب تمرين/مقال إذا الاثنين مطلوبين.
     *
     * @return array<int, array{type: class-string, id: int}>
     */
    private function buildDailySequence(
        Collection $exercises,
        Collection $articles,
        Collection $audioTracks,
    ): array {
        $sequence = [];
        $contentIndex = 0;
        $audioSlots = match ($audioTracks->count()) {
            0 => [],
            1 => [3],
            default => [1, 4],
        };
        $audioIndex = 0;

        for ($i = 0; $i < 7; $i++) {
            if (in_array($i, $audioSlots, true)) {
                $audioTrack = $audioTracks[$audioIndex++];
                $sequence[] = ['type' => AudioTrack::class, 'id' => $audioTrack->id];

                continue;
            }

            $preferArticle = $articles->isNotEmpty()
                && ($exercises->isEmpty() || $contentIndex % 2 === 1);

            if ($preferArticle) {
                $article = $articles[$contentIndex % $articles->count()];
                $sequence[] = ['type' => Article::class, 'id' => $article->id];
                $contentIndex++;

                continue;
            }

            $exercise = $exercises[$contentIndex % $exercises->count()];
            $sequence[] = ['type' => Exercise::class, 'id' => $exercise->id];
            $contentIndex++;
        }

        return $sequence;
    }
}
