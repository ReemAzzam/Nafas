<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\AudioTrackResource;
use App\Services\ImageStorageService;
use App\Services\SosCalmingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminExerciseResource extends JsonResource
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
            'media_type' => $this->media_type,
            'media_url' => $this->media_url,
            'media_credit' => $this->media_credit,
            'image_url' => app(ImageStorageService::class)->url($this->image_url),
            'optional_audio_track_id' => $this->optional_audio_track_id,
            'optional_audio' => $this->whenLoaded(
                'optionalAudioTrack',
                fn () => $this->optionalAudioTrack
                    ? AudioTrackResource::make($this->optionalAudioTrack)
                    : null
            ),
            'is_published' => $this->is_published,
            'is_sos_exercise' => in_array($this->slug, SosCalmingService::EXERCISE_SLUGS, true),
            'goals' => $this->whenLoaded('goals'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
