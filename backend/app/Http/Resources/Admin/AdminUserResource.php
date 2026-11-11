<?php

namespace App\Http\Resources\Admin;

use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'phone' => $this->formatted_phone,
            'profile_image_url' => app(ImageStorageService::class)->url($this->profile_image_url),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            'status' => $this->status,
            'role' => $this->role,
            'onboarding_status' => $this->onboarding_status,
            'is_anonymous' => $this->is_anonymous,
            'is_blocked' => $this->is_blocked,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'last_activity_at' => $this->last_activity_at,
            'counts' => [
                'mood_logs' => (int) ($this->mood_logs_count ?? 0),
                'activity_completions' => (int) ($this->activity_completions_count ?? 0),
                'journal_entries' => (int) ($this->journal_entries_count ?? 0),
                'comments' => (int) ($this->article_comments_count ?? 0),
                'comment_reports' => (int) ($this->article_comment_reports_count ?? 0),
            ],
            'goals' => $this->whenLoaded('goals'),
            'support_methods' => $this->whenLoaded('supportMethods'),
        ];
    }
}
