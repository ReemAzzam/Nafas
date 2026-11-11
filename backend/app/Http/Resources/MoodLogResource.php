<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MoodLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $suggestion = $this->currentSuggestion();

        return [
            'id' => $this->id,
            'mood_score' => $this->mood_score,
            'created_at' => $this->created_at,
            'factors' => $this->when(
                $this->relationLoaded('factors'),
                fn () => FactorResource::collection($this->getRelation('factors'))
            ),
            'suggestion' => $suggestion ? SuggestionResource::make($suggestion) : null,
        ];
    }
}
