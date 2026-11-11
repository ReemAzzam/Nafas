<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run the scheduler each minute in production to enforce retention for inactive users.
\Illuminate\Support\Facades\Schedule::call(function () {
    \App\Models\SupportMessage::where('created_at', '<', now()->subDays(30))->delete();
})->daily();

\Illuminate\Support\Facades\Artisan::command('nafas:check-media', function () {
    $missing = 0;
    foreach ([\App\Models\Article::class, \App\Models\Exercise::class, \App\Models\SosCounselor::class] as $model) {
        foreach ($model::query()->cursor() as $item) {
            $path = $item->image_url;
            if (!$path) { $this->warn(class_basename($model).' #'.$item->id.': empty image'); $missing++; continue; }
            if (preg_match('#^https?://#i', $path)) continue;
            $path = str_replace('\\', '/', trim($path));
            $path = preg_replace('#^/?(?:public/)?storage/app/public/#', '', $path);
            $path = preg_replace('#^/?(?:public/)?storage/#', '', $path);
            $path = preg_replace('#^/?public/#', '', $path);
            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists(ltrim($path, '/'))) {
                $this->warn(class_basename($model).' #'.$item->id.': missing '.$path); $missing++;
            }
        }
    }
    if (!is_link(public_path('storage'))) $this->warn('public/storage symlink is missing; run php artisan storage:link');
    $this->info('Missing or empty image records: '.$missing);
    return $missing ? 1 : 0;
});

Artisan::command('nafas:check-ai {--offline : Check settings without a provider request}', function () {
    $this->info('Gemini model: '.config('wellness.gemini_model'));
    $this->info('Cloud enabled: '.(config('wellness.cloud_enabled') ? 'yes' : 'no'));
    $hasKey = trim((string) config('wellness.gemini_key')) !== '';
    $this->info('API key configured: '.($hasKey ? 'yes' : 'no'));
    if ($this->option('offline')) return 0;
    if (!$hasKey || !config('wellness.cloud_enabled')) {
        $this->warn('Set GEMINI_API_KEY and WELLNESS_CLOUD_ENABLED in the existing .env, then clear cached config.');
        return 1;
    }
    $text = app(\App\Services\GeminiClient::class)->generate('Return a short Arabic greeting only.', [], 'مرحبا');
    if ($text === null) {
        $this->error('Gemini did not return a usable response. Check network, model access, API key, billing and quota. No credentials were printed.');
        return 1;
    }
    $this->info('Gemini connection succeeded with a synthetic test message.');
    return 0;
});

require __DIR__ . '/nafas_videos_console.php';
