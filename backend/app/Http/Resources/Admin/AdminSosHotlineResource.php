<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\SosHotlineResource;

class AdminSosHotlineResource extends SosHotlineResource
{
    public function toArray($request): array
    {
        return [
            ...parent::toArray($request),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
