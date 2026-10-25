<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

// Ada dua jenis halaman: Inertia (Vue) dan Blade (saat ini hanya design area).
// Klik menu dari halaman Inertia mengirim permintaan Inertia; bila jawabannya halaman Blade,
// Inertia menampilkannya di popup. Di sini permintaan itu diubah menjadi kunjungan halaman penuh.
class HalamanBladePenuh
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->header('X-Inertia')
            && $request->isMethod('GET')
            && ! $response->headers->has('X-Inertia')
            && $response->isSuccessful()
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return Inertia::location($request->fullUrl());
        }

        return $response;
    }
}
