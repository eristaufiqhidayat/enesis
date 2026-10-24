<?php

namespace App\Http\Middleware\Mobile;

use App\Support\Mobile\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Semua respons API mobile membawa X-Min-App-Version; aplikasi lebih lama dari versi itu
// (header X-App-Version) ditolak 426 upgrade_required. Permintaan selalu dianggap JSON.
class VersiAplikasi
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $minimum = (string) config('mobile.versi_minimum');
        $versi = $request->header('X-App-Version');

        $response = $versi && version_compare($versi, $minimum, '<')
            ? ApiError::respons('upgrade_required', "Versi aplikasi $versi sudah tidak didukung. Perbarui ke versi $minimum atau lebih baru.", 426)
            : $next($request);

        $response->headers->set('X-Min-App-Version', $minimum);

        return $response;
    }
}
