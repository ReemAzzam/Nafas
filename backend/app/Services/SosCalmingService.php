<?php

namespace App\Services;

use App\Models\Exercise;
use Illuminate\Support\Collection;

class SosCalmingService
{
    public const EXERCISE_SLUGS = [
        'calm-breathing',
        'grounding-5-4-3-2-1',
        'thought-dump',
    ];

    public function options(): Collection
    {
        $order = array_flip(self::EXERCISE_SLUGS);

        return Exercise::query()
            ->where('is_published', true)
            ->whereIn('slug', self::EXERCISE_SLUGS)
            ->get()
            ->sortBy(fn (Exercise $exercise) => $order[$exercise->slug])
            ->values();
    }
}
