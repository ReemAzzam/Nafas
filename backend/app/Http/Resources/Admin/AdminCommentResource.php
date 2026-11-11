<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'status' => $this->status,
            'moderation' => [
                'status' => $this->moderation_status,
                'score' => $this->moderation_score,
                'labels' => $this->moderation_labels ?? [],
                'moderated_at' => $this->moderated_at?->toISOString(),
            ],
            'author' => [
                'id' => $this->user_id,
                'display_name' => $this->user
                    ? ($this->user->display_name ?: 'مستخدم مجهول')
                    : 'حساب محذوف',
                'is_deleted' => $this->user?->trashed() ?? true,
            ],
            'article' => $this->whenLoaded('article', fn () => $this->article ? [
                'id' => $this->article->id,
                'title' => $this->article->title,
                'is_deleted' => $this->article->trashed(),
            ] : null),
            'reports_count' => (int) ($this->reports_count ?? 0),
            'pending_reports_count' => (int) ($this->pending_reports_count ?? 0),
            'reports' => AdminCommentReportResource::collection($this->whenLoaded('reports')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
