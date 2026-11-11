<?php

namespace App\Http\Resources;

use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AudioTrackResource extends JsonResource
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
            'file_size_bytes' => $this->file_size_bytes,
        ];
    }
}
