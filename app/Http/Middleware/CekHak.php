<?php

namespace App\Http\Middleware;

use App\Support\Akses\Akses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// hak:<menu>,<aksi>   mis. hak:user_sales,ubah
// hak:@slug,lihat     menu diambil dari parameter route "slug" (rute menu umum /{slug}).
// Juga mengeluarkan akun yang role-nya tidak boleh login web (mis. sales motoris: hanya HP).
class CekHak
{
    public function handle(Request $request, Closure $next, string $menu, string $aksi = 'lihat'): Response
    {
        $user = $request->user();

        if (! Akses::bolehWeb($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Akun ini hanya untuk aplikasi HP, tidak bisa membuka web Enesis.']);
        }

        if (str_starts_with($menu, '@')) {
            $menu = (string) $request->route(substr($menu, 1));
        }

        abort_unless(Akses::bisa($user, $menu, $aksi), 403, 'Anda tidak punya hak untuk membuka atau melakukan ini.');

        return $next($request);
    }
}
