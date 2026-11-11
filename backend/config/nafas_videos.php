<?php
return [
    'key' => env('YOUTUBE_API_KEY', ''),
    // Select playlists you trust; never derive a query from a user's chat.
    'playlists' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'YOUTUBE_PLAYLIST_IDS', 'PL6vDRyjGOU-MhcjPcv0j_3_kIvXAWtjUV'
    ))))),
    'timezone' => env('YOUTUBE_REFRESH_TIMEZONE', 'Asia/Damascus'),
    'count' => 4,
];
