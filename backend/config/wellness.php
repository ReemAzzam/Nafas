<?php
return [
    'cloud_enabled' => (bool) env('WELLNESS_CLOUD_ENABLED', false),
    'gemini_key' => env('GEMINI_API_KEY', ''),
    'gemini_model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    'daily_cloud_limit' => max(1, (int) env('WELLNESS_DAILY_CLOUD_LIMIT', 40)),
    'knowledge_path' => resource_path('wellness/knowledge.json'),
];
