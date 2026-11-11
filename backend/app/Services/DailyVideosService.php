<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DailyVideosService
{
    private array $diagnostic = ['code'=>'not_called', 'http_status'=>null, 'reason'=>null];
    public function diagnostics(): array { return $this->diagnostic; }

    public function feed(bool $force = false, bool $cacheOnly = false): array
    {
        $this->diagnostic = ['code'=>'not_called', 'http_status'=>null, 'reason'=>null];
        $zone = (string)config('nafas_videos.timezone', 'Asia/Damascus');
        try { $now = new \DateTimeImmutable('now', new \DateTimeZone($zone)); }
        catch (\Throwable $e) { $zone = 'Asia/Damascus'; $now = new \DateTimeImmutable('now', new \DateTimeZone($zone)); }
        $day = $now->format('Y-m-d');
        $refreshAfter = max(60, $now->modify('tomorrow')->setTime(0, 0)->getTimestamp() - $now->getTimestamp() + 5);
        $playlists = [];
        foreach ((array)config('nafas_videos.playlists', []) as $value) {
            if (!is_string($value)) continue;
            $id = trim($value);
            // Also accept a pasted YouTube playlist URL in the existing env setting.
            $host = strtolower((string)parse_url($id, PHP_URL_HOST));
            if (in_array($host, ['youtube.com','www.youtube.com','m.youtube.com'], true)) {
                parse_str((string)parse_url($id, PHP_URL_QUERY), $query); $id = $query['list'] ?? '';
            }
            if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{10,80}$/D', $id)) $playlists[$id] = $id;
        }
        $playlists = array_slice(array_values($playlists), 0, 2);
        $key = trim((string)config('nafas_videos.key'));
        if ($key === '' || $playlists === []) {
            $this->note($key === '' ? 'missing_key' : 'missing_playlists');
            return $this->response(null, 'not_configured', 60);
        }
        $base = 'nafas:daily-videos:v2:'.hash('sha256', implode(',', $playlists).'|'.$zone.'|'.hash('sha256', $key));
        $cache = Cache::store('file');
        $last = $cache->get($base);
        // Preserve the previous version's useful fallback during the first upgrade refresh.
        if (!is_array($last)) $last = $cache->get('nafas:daily-videos:v1:'.hash('sha256', implode(',', $playlists)));
        if (!is_array($last) || time() - (int)($last['synced_at'] ?? 0) >= 7 * 86400) $last = null;
        if ($cacheOnly) {
            // Phone requests never perform Google HTTP calls or acquire a refresh lock.
            // An independent CLI worker owns refreshes, including the first warm-up.
            $fresh = ($last['day'] ?? null) === $day && empty($last['partial'])
                && !$cache->has($base.':needs_refresh') && is_array($cache->get($base));
            $this->note($fresh ? 'cached' : 'refresh_in_progress');
            $state = $fresh ? (empty($last['items']) ? 'empty' : 'ready') : ($last ? 'stale' : 'refreshing');
            return $this->response($last, $state, $fresh ? $refreshAfter : 5);
        }
        // A partial result must be retried, even on the same day.
        if (!$force && ($last['day'] ?? null) === $day && empty($last['partial']) && !$cache->has($base.':needs_refresh')) {
            // Only a v2 record may prove today's current credentials worked.
            $current = $cache->get($base);
            if (is_array($current)) { $this->note('cached'); return $this->response($last, empty($last['items']) ? 'empty' : 'ready', $refreshAfter); }
        }
        $retry = max(30, min(300, (int)config('nafas_connections.youtube_retry_seconds', 60)));
        if (!$force && $cache->has($base.':retry')) {
            $this->note('retry_pending'); return $this->response($last, $last ? 'stale' : 'unavailable', $retry);
        }
        $lock = $cache->lock($base.':lock', 90);
        if (!$lock->get()) { $this->note('refresh_in_progress'); return $this->response($last, $last ? 'stale' : 'refreshing', 60); }
        try {
            $current = $cache->get($base);
            if (!$force && is_array($current) && ($current['day'] ?? '') === $day && empty($current['partial']) && !$cache->has($base.':needs_refresh')) {
                $this->note('cached'); return $this->response($current, empty($current['items']) ? 'empty' : 'ready', $refreshAfter);
            }
            $cache->put($base.':retry', true, $retry);
            $cache->put($base.':needs_refresh', true, 7 * 86400);
            $ids = []; $failed = false;
            foreach ($playlists as $playlist) {
                try {
                    $result = $this->request('playlistItems', ['part'=>'contentDetails', 'playlistId'=>$playlist, 'maxResults'=>50, 'key'=>$key]);
                    foreach ($result['items'] as $item) {
                        $id = $item['contentDetails']['videoId'] ?? '';
                        if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) $ids[$id] = $id;
                    }
                } catch (\Throwable $e) { $failed = true; }
            }
            $raw = [];
            foreach (array_chunk(array_values($ids), 50) as $chunk) {
                try {
                    $result = $this->request('videos', ['part'=>'snippet,contentDetails,status', 'id'=>implode(',', $chunk), 'key'=>$key]);
                    $raw = array_merge($raw, $result['items']);
                } catch (\Throwable $e) { $failed = true; }
            }
            $policy = new DailyVideoPolicy;
            $pool = $policy->normalize($raw);
            if ($failed && !$pool) return $this->response($last, $last ? 'stale' : 'unavailable', $retry);
            // Do not replace a previously usable feed with an empty provider/filter result.
            if (!$pool && !empty($last['items'])) {
                $this->note('no_eligible_videos'); return $this->response($last, 'stale', $retry);
            }
            $previousIds = ($last['day'] ?? null) === $day ? [] : array_column($last['items'] ?? [], 'id');
            $selected = $policy->select($pool, $day, $previousIds, (int)config('nafas_videos.count', 4));
            $fresh = ['day'=>$day, 'synced_at'=>time(), 'items'=>$selected, 'partial'=>$failed];
            $cache->put($base, $fresh, 7 * 86400);
            if (!$failed) { $cache->forget($base.':retry'); $cache->forget($base.':needs_refresh'); $this->note($selected ? 'ok' : 'no_eligible_videos'); }
            return $this->response($fresh, $failed ? 'stale' : ($selected ? 'ready' : 'empty'), $failed ? $retry : $refreshAfter);
        } finally { $lock->release(); }
    }
    private function request(string $resource, array $query): array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(max(1, min(5, (int)config('nafas_connections.youtube_connect_timeout', 4))))
                ->timeout(max(4, min(8, (int)config('nafas_connections.youtube_timeout', 7))))
                ->withOptions(['allow_redirects'=>false])
                ->get('https://www.googleapis.com/youtube/v3/'.$resource, $query);
            $data = $response->json();
            if (!$response->successful()) {
                $reason = is_array($data) ? ($data['error']['errors'][0]['reason'] ?? null) : null;
                $allowed = ['quotaExceeded','dailyLimitExceeded','keyInvalid','accessNotConfigured','forbidden',
                    'ipRefererBlocked','playlistNotFound','playlistItemsNotAccessible','playlistOperationUnsupported','rateLimitExceeded'];
                $this->note('provider_rejected', $response->status(), in_array($reason, $allowed, true) ? $reason : null);
                throw new \RuntimeException('provider_rejected');
            }
            if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
                $this->note('invalid_response', $response->status()); throw new \RuntimeException('invalid_response');
            }
            return $data;
        } catch (\Throwable $e) {
            if (!in_array($e->getMessage(), ['provider_rejected','invalid_response'], true)) {
                $raw = strtolower($e->getMessage());
                $this->note(str_contains($raw, 'curl error 60') ? 'tls_certificate'
                    : (str_contains($raw, 'curl error 6:') ? 'dns'
                    : (str_contains($raw, 'curl error 28') || str_contains($raw, 'timed out') ? 'timeout' : 'connection_failed')));
            }
            throw new \RuntimeException('Video provider unavailable');
        }
    }
    private function note(string $code, ?int $status = null, ?string $reason = null): void
    {
        $this->diagnostic = ['code'=>$code, 'http_status'=>$status, 'reason'=>$reason];
        if (!in_array($code, ['ok','cached','refresh_in_progress','retry_pending'], true)) Log::warning('nafas.youtube', $this->diagnostic);
    }
    private function response(?array $record, string $state, int $refreshAfter): array
    {
        return ['items'=>$record['items'] ?? [], 'meta'=>['state'=>$state, 'day'=>$record['day'] ?? null,
            'updated_at'=>isset($record['synced_at']) ? gmdate('c', $record['synced_at']) : null,
            'refresh_after_seconds'=>$refreshAfter]];
    }
}
