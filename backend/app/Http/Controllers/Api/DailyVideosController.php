<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\DailyVideosService;
class DailyVideosController extends Controller
{
    public function index(DailyVideosService $videos)
    {
        try { $feed = $videos->feed(false, true); }
        catch (\Throwable $e) { $feed = ['items'=>[], 'meta'=>['state'=>'unavailable','refresh_after_seconds'=>900]]; }
        return response()->json(['success'=>true, 'data'=>$feed['items'], 'meta'=>$feed['meta']])
            ->header('Cache-Control', 'private, no-store');
    }
}
