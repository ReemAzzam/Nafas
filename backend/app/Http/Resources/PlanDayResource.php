<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_number' => $this->day_number,
            'date' => $this->date->toDateString(),
            'is_completed' => $this->is_completed,
            'activities' => PlanActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
