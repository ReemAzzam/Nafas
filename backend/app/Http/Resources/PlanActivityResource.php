<?php

namespace App\Http\Resources;

use App\Models\Article;
use App\Models\AudioTrack;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => match ($this->activity_type) {
                Exercise::class => 'exercise',
                Article::class => 'article',
                AudioTrack::class => 'audio_track',
                default => 'unknown',
            },
            'activity_type' => $this->activity_type,
            'activity_id' => $this->activity_id,
            'is_completed' => $this->is_completed,
            'activity' => match (true) {
                $this->activity instanceof Exercise => ExerciseResource::make($this->activity),
                $this->activity instanceof Article => ArticleResource::make($this->activity),
                $this->activity instanceof AudioTrack => AudioTrackResource::make($this->activity),
                default => null,
            },
        ];
    }
}
