<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MoodSuggestionService
{
    public function suggest(
        User $user,
        int $moodScore,
        Collection $factorSlugs,
        array $exclude = []
    ): ?Model {
        $methodSlugs = $user->supportMethods()->pluck('support_methods.slug');
        $goalIds = $user->goals()->pluck('goals.id');

        $feedback = \App\Models\RecommendationFeedback::where('user_id', $user->id)->get()->keyBy(fn ($f) => $f->item_type.':'.$f->item_id);
        $completed = $user->activityCompletions()->where('created_at', '>=', now()->subDays(7))->get();
        $candidates = $this->exercises()
            ->concat($this->articles())
            ->reject(fn (Model $item) => in_array($this->key($item), $exclude, true) || ($feedback->get($this->key($item))?->rating ?? 0) < 0);

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->map(fn (Model $item) => [
                'item' => $item,
                'score' => $this->score($item, $moodScore, $factorSlugs, $methodSlugs, $goalIds)
                    + (($feedback->get($this->key($item))?->rating ?? 0) > 0 ? 3 : 0)
                    - min(4, $completed->where('activity_type', get_class($item))->where('activity_id', $item->id)->count()) * 2,
            ])
            ->sortByDesc('score')
            ->first()['item'];
    }

    public function key(Model $item): string
    {
        return $item instanceof Article
            ? 'article:'.$item->id
            : 'exercise:'.$item->id;
    }

    public function type(Model $item): string
    {
        return $item instanceof Article ? 'article' : 'exercise';
    }

    private function exercises(): Collection
    {
        return Exercise::query()->where('is_published', true)->with('goals')->orderBy('id')->get();
    }

    private function articles(): Collection
    {
        return Article::query()->where('is_published', true)->with('goals')->orderBy('id')->get();
    }

    private function score(
        Model $item,
        int $moodScore,
        Collection $factorSlugs,
        Collection $methodSlugs,
        Collection $goalIds
    ): float {
        $score = 1.0;

        if ($item->goals->contains(fn ($goal) => $goalIds->contains($goal->id))) {
            $score += 3;
        }

        if ($item instanceof Exercise) {
            if (in_array($item->type, $this->typesForMood($moodScore), true)) {
                $score += 2;
            }
            if ($factorSlugs->contains('overthinking') && in_array($item->type, ['grounding', 'journaling'], true)) {
                $score += 2.5;
            }
            if ($factorSlugs->contains('restless_sleep') && $item->type === 'relaxation') {
                $score += 2.5;
            }
            if ($factorSlugs->intersect(['study_work', 'procrastination'])->isNotEmpty() && $item->type === 'breathing') {
                $score += 2;
            }
            if ($factorSlugs->contains('boredom') && in_array($item->type, ['grounding', 'journaling'], true)) {
                $score += 1.5;
            }
            if ($methodSlugs->contains('writing') && $item->type === 'journaling') {
                $score += 3;
            }
            if ($methodSlugs->intersect(['exercises', 'listening', 'practical'])->isNotEmpty()) {
                $score += 1.5;
            }
            if ($methodSlugs->contains('reading') && $methodSlugs->intersect(['exercises', 'writing', 'practical'])->isEmpty()) {
                $score -= 1;
            }
        } else {
            if ($methodSlugs->contains('reading')) {
                $score += 3;
            }
            if ($moodScore >= 4) {
                $score += 1.5;
            }
            if ($factorSlugs->intersect(['family', 'relationships', 'future_anxiety', 'social_media'])->isNotEmpty()) {
                $score += 2;
            }
            if ($moodScore <= 2 && $methodSlugs->intersect(['exercises', 'listening'])->isNotEmpty()) {
                $score -= 0.5;
            }
        }

        return $score; // Stable, explainable ranking without random jitter.
    }

    private function typesForMood(int $moodScore): array
    {
        return match (true) {
            $moodScore <= 2 => ['breathing', 'grounding', 'relaxation'],
            $moodScore === 3 => ['breathing', 'grounding', 'journaling', 'relaxation'],
            default => ['journaling', 'relaxation', 'breathing'],
        };
    }
}
