<?php
namespace App\Services;

/** Pure selection/validation logic; never fabricates video IDs, titles or duration. */
class DailyVideoPolicy
{
    public function normalize(array $items): array
    {
        $valid = [];
        foreach ($items as $item) {
            $id = $item['id'] ?? '';
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) continue;
            $snippet = $item['snippet'] ?? [];
            $status = $item['status'] ?? [];
            if (($status['privacyStatus'] ?? '') !== 'public' || ($status['uploadStatus'] ?? '') !== 'processed') continue;
            if (($snippet['liveBroadcastContent'] ?? 'none') !== 'none') continue;
            $duration = $item['contentDetails']['duration'] ?? '';
            if (!is_string($duration) || !preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/D', $duration, $parts)) continue;
            $seconds = (int)($parts[1] ?? 0) * 3600 + (int)($parts[2] ?? 0) * 60 + (int)($parts[3] ?? 0);
            if ($seconds < 30 || $seconds > 1800) continue;
            $title = html_entity_decode((string)($snippet['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $channel = html_entity_decode((string)($snippet['channelTitle'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (trim($title) === '' || trim($channel) === '') continue;
            $image = null;
            foreach (['high', 'medium', 'default'] as $size) {
                $url = $snippet['thumbnails'][$size]['url'] ?? null;
                if (is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https'
                    && in_array(parse_url($url, PHP_URL_HOST), ['i.ytimg.com', 'img.youtube.com'], true)) {
                    $image = $url; break;
                }
            }
            $valid[$id] = ['id' => $id, 'title' => $title, 'channel' => $channel,
                'url' => 'https://www.youtube.com/watch?v='.$id, 'image_url' => $image,
                'duration_seconds' => $seconds];
        }
        return array_values($valid);
    }

    public function select(array $pool, string $day, array $previousIds, int $count = 4): array
    {
        $count = max(1, min(8, $count));
        usort($pool, function (array $a, array $b) use ($day, $previousIds): int {
            // Prefer IDs not shown on the previous successful day, then stable daily order.
            $aSeen = in_array($a['id'], $previousIds, true);
            $bSeen = in_array($b['id'], $previousIds, true);
            if ($aSeen !== $bSeen) return $aSeen <=> $bSeen;
            return strcmp(hash('sha256', $day.':'.$a['id']), hash('sha256', $day.':'.$b['id']));
        });
        return array_slice($pool, 0, $count);
    }
}
