<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Akun dengan password awal (wajib_ganti_password) hanya boleh membuka halaman ganti password.
class WajibGantiPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->wajib_ganti_password) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                redirect()->setIntendedUrl($request->fullUrl());
            }

            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
