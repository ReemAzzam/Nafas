<?php
namespace App\Console\Commands;

use App\Services\{GeminiClient, DailyVideosService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckNafasConnections extends Command
{
    protected $signature = 'nafas:check-connections {--gemini : One synthetic Gemini request} {--youtube : Check daily video refresh} {--force : Bypass video refresh cooldown} {--models : List accessible text-generation model names}';
    protected $description = 'Diagnose provider connectivity without printing keys or private conversation data';

    public function handle(): int
    {
        $any = $this->option('gemini') || $this->option('youtube') || $this->option('models');
        if (!$any) {
            $this->line('Use --gemini and/or --youtube. Add --force to refresh videos now.');
            $this->line('No external request sent.'); return self::SUCCESS;
        }
        $ok = true;
        if ($this->option('gemini')) {
            $enabled = (bool)config('wellness.cloud_enabled');
            $key = trim((string)config('wellness.gemini_key'));
            $model = (string)config('wellness.gemini_model');
            $safeModel = preg_match('#^(models/)?gemini-[a-z0-9.-]+$#D', $model) ? $model : '[invalid model setting]';
            $this->line('Gemini model: '.$safeModel);
            $this->line('Cloud enabled: '.($enabled ? 'yes' : 'no'));
            $this->line('API key configured: '.($key !== '' ? 'yes' : 'no'));
            if (!$enabled || $key === '') {
                $this->warn('Set WELLNESS_CLOUD_ENABLED=true and GEMINI_API_KEY in your existing .env, then run php artisan config:clear.'); $ok = false;
            } else {
                $client = app(GeminiClient::class);
                $text = $client->generate('Return only one short Arabic greeting.', [], 'مرحبا');
                $details = $client->diagnostics();
                $this->line('Gemini result: '.$details['code'].' | HTTP: '.($details['http_status'] ?? '-').' | finish: '.($details['finish_reason'] ?? '-'));
                if ($text !== null) {
                    $this->info('Gemini connection succeeded with a synthetic message.');
                    $this->line('In the app enable the separate Google Gemini consent checkbox before sending a NEW message.');
                } else { $this->hint($details['code']); $ok = false; }
            }
        }
        if ($this->option('models')) $ok = $this->models() && $ok;
        if ($this->option('youtube')) {
            try {
                $videos = app(DailyVideosService::class);
                $feed = $videos->feed((bool)$this->option('force'));
                $details = $videos->diagnostics();
                $this->line('YouTube state: '.$feed['meta']['state'].' | videos: '.count($feed['items']));
                $this->line('YouTube result: '.$details['code'].' | HTTP: '.($details['http_status'] ?? '-').' | reason: '.($details['reason'] ?? '-'));
                if ($details['reason']) $this->hint($details['reason']);
                else $this->hint($details['code']);
                if ($feed['meta']['state'] !== 'ready') $ok = false;
            } catch (\Throwable $e) {
                $this->error('YouTube refresh could not complete. Check writable storage/framework/cache/data, installed files and Laravel log.'); $ok = false;
            }
        }
        return $ok ? self::SUCCESS : self::FAILURE;
    }
    private function hint(string $code): void
    {
        $text = match ($code) {
            'missing_key' => 'Set the provider API key in the existing .env, then run php artisan config:clear.',
            'missing_playlists' => 'Set YOUTUBE_PLAYLIST_IDS to one or two public playlist IDs or YouTube playlist URLs.',
            'invalid_key', 'keyInvalid', 'key_blocked' => 'Replace the invalid or blocked provider key in the existing .env; do not paste the key in chat.',
            'access_denied', 'ipRefererBlocked', 'forbidden' => 'Review key restrictions for server-side PHP requests and enable the correct API. A browser-referrer or Android-app restriction cannot authenticate this server request.',
            'accessNotConfigured' => 'Enable YouTube Data API v3 in the Google Cloud project that owns this key.',
            'quota_or_rate_limit', 'quotaExceeded', 'dailyLimitExceeded', 'rateLimitExceeded' => 'Provider quota or rate limit reached. Review usage/billing and wait for reset; rebuilding the APK will not reset quota.',
            'model_unavailable', 'invalid_model' => 'Run php artisan nafas:check-connections --models; choose an accessible generateContent model and set GEMINI_MODEL, then run php artisan config:clear.',
            'region_unavailable' => 'The provider does not support this server location. Use a supported deployment location.',
            'timeout', 'dns', 'connection_failed' => 'Check outbound internet, VPN/proxy and DNS on the computer running PHP. Retry the diagnostic after restoring connectivity.',
            'tls_certificate' => 'Configure a current trusted CA bundle for this PHP installation. Keep TLS certificate verification enabled.',
            'output_limit' => 'The model exhausted the output budget. Check its thinking settings or select an accessible lighter model; nafas_connections.php now allows 2048 output tokens.',
            'provider_unavailable' => 'Provider temporarily unavailable; retry later.',
            'playlistNotFound', 'playlistItemsNotAccessible', 'playlistOperationUnsupported' => 'Check YOUTUBE_PLAYLIST_IDS: each playlist must exist and be publicly accessible through the API.',
            'no_eligible_videos' => 'The playlist returned no eligible public processed videos between 30 seconds and 30 minutes. Check playlist contents.',
            'retry_pending' => 'A refresh failed recently. Retry with --youtube --force to diagnose now.',
            'ok', 'cached', 'refresh_in_progress' => '',
            default => 'Check the reported code in storage/logs/laravel.log. Only safe diagnostic codes are logged.',
        };
        if ($text !== '') $this->warn($text);
    }
    private function models(): bool
    {
        $key = trim((string)config('wellness.gemini_key'));
        if ($key === '') { $this->hint('missing_key'); return false; }
        try {
            // Listing is diagnostic only. Never silently switch a user's selected model.
            $response = Http::withHeaders(['x-goog-api-key'=>$key])->acceptJson()
                ->connectTimeout(6)->timeout(15)->withOptions(['allow_redirects'=>false])
                ->get('https://generativelanguage.googleapis.com/v1beta/models', ['pageSize'=>1000]);
            if (!$response->successful()) { $this->error('Model list failed. HTTP: '.$response->status()); return false; }
            $count = 0;
            foreach (($response->json('models') ?? []) as $model) {
                $name = $model['name'] ?? '';
                if (is_string($name) && preg_match('#^models/gemini-[a-z0-9.-]+$#D', $name)
                    && in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true)) {
                    $this->line(substr($name, 7)); $count++;
                }
            }
            $this->line('Accessible text models: '.$count);
            return $count > 0;
        } catch (\Throwable $e) { $this->error('Model list connection failed; no credentials printed.'); return false; }
    }
}
