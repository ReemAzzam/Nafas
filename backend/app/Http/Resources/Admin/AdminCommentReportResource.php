<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCommentReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'status' => $this->status,
            'reporter' => $this->whenLoaded('user', fn () => [
                'id' => $this->user_id,
                'display_name' => $this->user?->display_name ?? 'حساب محذوف',
            ]),
            'admin_note' => $this->admin_note,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
