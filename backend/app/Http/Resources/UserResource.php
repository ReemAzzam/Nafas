<?php

namespace App\Http\Resources;

use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'country_code' => $this->country_code,
            'formatted_phone' => $this->formatted_phone,
            'profile_image_url' => app(ImageStorageService::class)->url($this->profile_image_url),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            'status' => $this->status,
            'onboarding_status' => $this->onboarding_status,
            'is_anonymous' => $this->is_anonymous,
            'role' => $this->role,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
