<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('transit-submit', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('transit.rate_limits.ip')))->by($request->ip());
        });
    }
}
