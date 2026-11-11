<?php

namespace App\Http\Resources;

use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'content' => $this->when($request->routeIs('articles.show'), $this->content),
            'author_name' => $this->author_name,
            'image_url' => app(ImageStorageService::class)->url($this->image_url ?? null),
            'is_favorite' => $this->relationLoaded('favoriteUsers')
                && $this->favoriteUsers->isNotEmpty(),
        ];
    }
}
