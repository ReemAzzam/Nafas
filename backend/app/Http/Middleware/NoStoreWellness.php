<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class NoStoreWellness
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
