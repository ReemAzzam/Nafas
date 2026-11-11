<?php
use Illuminate\Support\Facades\Schedule;
$zone = (string)config('nafas_videos.timezone', 'Asia/Damascus');
try { new \DateTimeZone($zone); } catch (\Throwable $e) { $zone = 'Asia/Damascus'; }
Schedule::command('nafas:refresh-videos')->dailyAt('06:00')
    ->timezone($zone)
    ->withoutOverlapping();
