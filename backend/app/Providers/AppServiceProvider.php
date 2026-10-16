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
        // The demo is public without authentication, so writes are limited per IP.
        foreach (['uploads', 'deletes'] as $name) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute(config("stockpress.rate_limits.{$name}"))
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => response()->json(
                    ['message' => 'Zbyt wiele żądań. Spróbuj ponownie za chwilę.'],
                    429,
                    $headers,
                )));
        }
    }
}
