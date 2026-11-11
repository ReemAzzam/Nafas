<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Exercise;
use App\Services\ImageStorageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ContentImageSeeder extends Seeder
{
    public function run(): void
    {
        $storage = app(ImageStorageService::class);

        foreach ($this->exerciseMap() as $slug => $filename) {
            $exercise = Exercise::query()->where('slug', $slug)->first();
            $source = $this->fixture('exercises/'.$filename);

            if (! $exercise) {
                continue;
            }

            if (! is_file($source)) {
                throw new RuntimeException("Exercise image fixture is missing: {$source}");
            }

            if ($this->hasValidImage($exercise->image_url)) {
                continue;
            }

            $exercise->update([
                'image_url' => $storage->storeFixture(
                    $source,
                    ImageStorageService::FOLDER_EXERCISES,
                    $filename
                ),
            ]);
        }

        foreach ($this->articleMap() as $slug => $filename) {
            $article = Article::query()->where('slug', $slug)->first();
            $source = $this->fixture('articles/'.$filename);

            if (! $article) {
                continue;
            }

            if (! is_file($source)) {
                throw new RuntimeException("Article image fixture is missing: {$source}");
            }

            if ($this->hasValidImage($article->image_url)) {
                continue;
            }

            $article->update([
                'image_url' => $storage->storeFixture(
                    $source,
                    ImageStorageService::FOLDER_ARTICLES,
                    $filename
                ),
            ]);
        }
    }

    private function fixture(string $relative): string
    {
        return database_path('seeders/fixtures/images/'.$relative);
    }

    private function hasValidImage(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return in_array(parse_url($path, PHP_URL_SCHEME), ['http', 'https'], true);
        }

        $disk = Storage::disk(ImageStorageService::DISK);
        if (! $disk->exists($path)) {
            return false;
        }

        $mimeType = $disk->mimeType($path);

        return is_string($mimeType) && str_starts_with($mimeType, 'image/');
    }

    private function exerciseMap(): array
    {
        return [
            'calm-breathing' => 'breathing.png',
            'grounding-5-4-3-2-1' => 'grounding.png',
            'thought-dump' => 'journaling.png',
            'calm-sleep-routine' => 'sleep.png',
            'small-step' => 'small-step.png',
            'shoulder-relaxation' => 'shoulders.png',
            'weekly-review' => 'weekly-review.png',
            'hunger-or-emotions' => 'emotional-eating.png',
            'mindful-meditation' => 'mindful-meditation.png',
            'mindful-walking' => 'mindful-walking.png',
            'feet-grounding' => 'feet-grounding.png',
            'morning-daylight-pause' => 'morning-daylight.png',
            'kind-mirror-talk' => 'mirror-self-talk.png',
        ];
    }

    private function articleMap(): array
    {
        return [
            'when-thoughts-crowd' => 'crowded-thoughts.png',
            'calmer-sleep-habits' => 'calmer-sleep.png',
            'exams-are-not-the-whole-story' => 'exams.png',
            'mood-changes-are-ok' => 'mood-ok.png',
            'emotional-eating-pause' => 'emotional-eating.png',
            'one-minute-focus' => 'one-minute-focus.png',
            'when-we-lose-a-parent' => 'mood-ok.png',
            'grief-comes-in-waves' => 'mood-ok.png',
            'time-management-when-energy-is-low' => 'one-minute-focus.png',
            'how-to-start-without-motivation' => 'one-minute-focus.png',
            'procrastination-is-not-always-laziness' => 'one-minute-focus.png',
            'focus-is-not-a-machine' => 'exams.png',
            'when-thinking-becomes-a-loop' => 'crowded-thoughts.png',
            'future-anxiety-in-uncertain-times' => 'mood-ok.png',
            'you-are-more-than-your-results' => 'exams.png',
            'steady-your-sleep-rhythm' => 'calmer-sleep.png',
            'when-social-comparison-feels-heavy' => 'mood-ok.png',
            'family-conflict-without-losing-yourself' => 'mood-ok.png',
            'healthy-relationships-do-not-erase-you' => 'mood-ok.png',
            'after-a-relationship-ends' => 'mood-ok.png',
            'boredom-is-not-always-an-empty-space' => 'calmer-sleep.png',
            'emotional-eating-without-punishment' => 'emotional-eating.png',
            'low-mood-and-small-steps' => 'mood-ok.png',
            'asking-for-help-is-not-a-last-resort' => 'mood-ok.png',
        ];
    }
}
