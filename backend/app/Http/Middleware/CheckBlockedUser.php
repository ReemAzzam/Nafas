<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBlockedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_blocked) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'تم حظر هذا الحساب لمخالفة الشروط.',
            ], 403);
        }

        return $next($request);
    }
}
