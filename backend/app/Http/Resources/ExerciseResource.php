<?php

namespace App\Http\Resources;

use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'duration_minutes' => $this->duration_minutes,
            'content_steps' => $this->content_steps,
            'media' => $this->media_url ? [
                'type' => $this->media_type,
                'url' => $this->media_url,
                'credit' => $this->media_credit,
            ] : null,
            'image_url' => app(ImageStorageService::class)->url($this->image_url ?? null),
            'optional_audio' => $this->relationLoaded('optionalAudioTrack')
                && $this->optionalAudioTrack?->is_published
                    ? AudioTrackResource::make($this->optionalAudioTrack)
                    : null,
        ];
    }
}
