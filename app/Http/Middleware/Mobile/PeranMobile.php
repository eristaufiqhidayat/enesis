<?php

namespace App\Http\Middleware\Mobile;

use App\Support\Mobile\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// mobile.peran            : akun sudah ganti password awal dan terhubung ke karyawan.
// mobile.peran:sales,...  : sekaligus membatasi kategori akun.
class PeranMobile
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = $request->user();

        if ($user->wajib_ganti_password) {
            throw new ApiError('password_change_required', 'Ganti password awal dulu sebelum memakai aplikasi.', 403);
        }

        if (! $user->karyawan_id) {
            throw new ApiError('no_employee_link', 'Akun belum terhubung ke data karyawan. Hubungi admin.', 403);
        }

        if ($peran !== [] && ! in_array($user->kategori, $peran, true)) {
            throw new ApiError('forbidden', 'Fitur ini tidak tersedia untuk akun Anda.', 403);
        }

        return $next($request);
    }
}
