<?php

namespace App\Providers;

use App\Support\Mobile\ApiError;
use App\Support\Mobile\TokenPerangkat;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        // Guard "mobile": token Bearer per HP (lihat App\Support\Mobile\TokenPerangkat).
        Auth::viaRequest('mobile-token', fn (Request $request) => TokenPerangkat::user($request));

        // API mobile: 120 permintaan/menit per token.
        RateLimiter::for('mobile', fn (Request $request) => Limit::perMinute(config('mobile.batas_per_menit'))
            ->by('mobile:'.(TokenPerangkat::token($request)->id ?? $request->ip()))
            ->response(fn (Request $request, array $headers) => ApiError::respons(
                'too_many_requests', 'Terlalu banyak permintaan. Coba lagi sebentar.', 429, [], $headers,
            )));
    }
}
