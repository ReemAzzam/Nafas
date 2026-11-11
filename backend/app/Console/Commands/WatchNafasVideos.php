<?php
namespace App\Console\Commands;

use App\Services\DailyVideosService;
use Illuminate\Console\Command;

class WatchNafasVideos extends Command
{
    protected $signature = 'nafas:video-worker {--once : Refresh if due, then exit}';
    protected $description = 'Refresh daily videos outside phone requests; keep running in a separate terminal';
    public function handle(DailyVideosService $videos): int
    {
        $previous = null;
        do {
            try {
                $feed = $videos->feed(); // Existing daily cache, retry cooldown and lock apply.
                $state = $feed['meta']['state'];
                $summary = $state.' | videos: '.count($feed['items']).' | day: '.($feed['meta']['day'] ?? '-');
                if ($summary !== $previous) { $this->line('YouTube worker: '.$summary); $previous = $summary; }
                if ($this->option('once')) return $state === 'ready' ? self::SUCCESS : self::FAILURE;
            } catch (\Throwable $e) {
                if ($previous !== 'worker_error') $this->warn('Video refresh failed. Check writable file cache and provider configuration. No keys printed.');
                $previous = 'worker_error';
                if ($this->option('once')) return self::FAILURE;
            }
            sleep(60);
        } while (true);
    }
}
