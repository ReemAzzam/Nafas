<?php

namespace App\Http\Resources\Admin;

use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'content' => $this->content,
            'author_name' => $this->author_name,
            'image_url' => app(ImageStorageService::class)->url($this->image_url),
            'is_published' => $this->is_published,
            'goals' => $this->whenLoaded('goals'),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'favorites_count' => (int) ($this->favorite_users_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
