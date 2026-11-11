<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'body' => $this->body,
            'word_count' => $this->wordCount(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function wordCount(): int
    {
        return count(preg_split(
            '/\s+/u',
            trim($this->body),
            -1,
            PREG_SPLIT_NO_EMPTY
        ));
    }
}
