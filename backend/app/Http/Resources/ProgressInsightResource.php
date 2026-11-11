<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressInsightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'period' => $this->resource['period'],
            'summary' => $this->resource['summary'],
            'mood_chart' => $this->resource['mood_chart'],
            'top_factors' => $this->resource['top_factors'],
        ];
    }
}
