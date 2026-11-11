<?php

namespace App\Http\Resources;

use App\Models\Article;
use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isArticle = $this->resource instanceof Article;

        return [
            'type' => $isArticle ? 'article' : 'exercise',
            'id' => $this->id,
            'title' => $this->title,
            'description' => $isArticle ? $this->summary : $this->description,
            'duration_minutes' => $isArticle ? null : $this->duration_minutes,
            'exercise_type' => $isArticle ? null : $this->type,
            // 'image_url'         => app(ImageStorageService::class)->url($this->image_url ?? null),
        ];
    }
}
