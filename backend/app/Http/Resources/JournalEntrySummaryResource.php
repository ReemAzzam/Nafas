<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class JournalEntrySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'excerpt' => Str::limit($this->body, 120),
            'word_count' => count(preg_split(
                '/\s+/u',
                trim($this->body),
                -1,
                PREG_SPLIT_NO_EMPTY
            )),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
