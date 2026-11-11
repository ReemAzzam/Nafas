<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => [
                'display_name' => $this->user
                    ? ($this->user->display_name ?: 'مستخدم مجهول')
                    : 'حساب محذوف',
            ],
            'likes_count' => (int) ($this->likes_count ?? 0),
            'is_liked' => $this->relationLoaded('likes') && $this->likes->isNotEmpty(),
            'is_mine' => $request->user()?->id === $this->user_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
