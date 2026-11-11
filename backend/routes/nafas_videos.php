<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DailyVideosController;
Route::middleware(['auth:sanctum', 'blocked', 'throttle:60,1'])
    ->get('/v1/videos/daily', [DailyVideosController::class, 'index']);
