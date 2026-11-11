<?php
namespace App\Console\Commands;
use App\Services\DailyVideosService;
use Illuminate\Console\Command;
class RefreshDailyVideos extends Command
{
    protected $signature = 'nafas:refresh-videos {--force : Refresh today again; consumes YouTube quota}';
    protected $description = 'Refresh the daily public YouTube feed without exposing the API key';
    public function handle(DailyVideosService $videos): int
    {
        try { $feed = $videos->feed((bool)$this->option('force')); }
        catch (\Throwable $e) {
            $this->error('Refresh failed. Check writable storage/framework/cache/data and configuration. No credentials printed.');
            return self::FAILURE;
        }
        $this->line('State: '.$feed['meta']['state']);
        $this->line('Videos: '.count($feed['items']));
        $this->line('Selection day: '.($feed['meta']['day'] ?? '-'));
        if ($feed['meta']['state'] === 'not_configured') $this->warn('Enable YouTube Data API v3 and set YOUTUBE_API_KEY and YOUTUBE_PLAYLIST_IDS in the existing .env; run optimize:clear.');
        if (in_array($feed['meta']['state'], ['unavailable','stale'], true)) $this->warn('Check API access, key restrictions, quota, playlist visibility and network. Provider errors and keys are not printed.');
        return in_array($feed['meta']['state'], ['ready','empty'], true) ? self::SUCCESS : self::FAILURE;
    }
}
