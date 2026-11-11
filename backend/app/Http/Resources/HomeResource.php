<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'display_name' => $this->resource['user']->display_name,
                'onboarding_status' => $this->resource['user']->onboarding_status,
            ],
            'mood' => [
                'has_checked_in_today' => $this->resource['has_checked_in_today'],
                'latest' => $this->resource['latest_mood']
                    ? MoodLogResource::make($this->resource['latest_mood'])
                    : null,
            ],
            'current_plan' => $this->resource['current_plan'],
            'quick_support' => [
                'calming_exercise' => $this->resource['calming_exercise']
                    ? ExerciseResource::make($this->resource['calming_exercise'])
                    : null,
                'featured_article' => $this->resource['featured_article']
                    ? ArticleResource::make($this->resource['featured_article'])
                    : null,
            ],
        ];
    }
}
