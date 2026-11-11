<?php

namespace App\Http\Resources\Admin;

use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAudioTrackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'duration_seconds' => $this->duration_seconds,
            'audio_url' => route('audio-tracks.stream', ['audioTrack' => $this->slug]),
            'image_url' => app(ImageStorageService::class)->url($this->image_url),
            'credit' => $this->credit,
            'license_type' => $this->license_type,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
