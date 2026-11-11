<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip()));

        $forUser = static fn (Request $request): string => (string) (
            $request->user()?->getAuthIdentifier() ?? $request->ip()
        );

        RateLimiter::for('profile-email-request', fn (Request $request) => Limit::perMinute(3)
            ->by($forUser($request)));
        RateLimiter::for('profile-email-verify', fn (Request $request) => Limit::perMinute(5)
            ->by($forUser($request)));
        RateLimiter::for('mood-logs', fn (Request $request) => Limit::perMinute(10)
            ->by($forUser($request)));
        RateLimiter::for('article-comments', fn (Request $request) => Limit::perMinute(5)
            ->by($forUser($request)));
        RateLimiter::for('comment-likes', fn (Request $request) => Limit::perMinute(30)
            ->by($forUser($request)));
        RateLimiter::for('comment-reports', fn (Request $request) => Limit::perMinute(10)
            ->by($forUser($request)));
        RateLimiter::for('journals', fn (Request $request) => Limit::perMinute(10)
            ->by($forUser($request)));
        RateLimiter::for('feedback', fn (Request $request) => Limit::perMinute(3)
            ->by($forUser($request)));
    }
}
