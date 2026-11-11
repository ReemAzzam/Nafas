<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'message' => $this->message,
            'status' => $this->status,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'display_name' => $this->user->trashed()
                    ? 'حساب محذوف'
                    : ($this->user->display_name ?: 'مستخدم مجهول'),
                'is_deleted' => $this->user->trashed(),
            ] : null),
            'admin_note' => $this->admin_note,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
