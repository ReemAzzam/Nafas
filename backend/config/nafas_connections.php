<?php
// Keep keys and the selected model in the existing wellness/nafas_videos config.
return [
    'gemini_timeout' => 25,
    'gemini_connect_timeout' => 6,
    // Includes the model's internal thinking budget; 600 could exhaust it before an answer.
    'gemini_output_tokens' => 2048,
    'youtube_timeout' => 7,
    'youtube_connect_timeout' => 4,
    'youtube_retry_seconds' => 60,
];
