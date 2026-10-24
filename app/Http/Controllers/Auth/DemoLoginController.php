<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

// "Demo masuk cepat": masuk tanpa password sebagai akun demo per peran. Hanya bila
// config auth.demo_login aktif; tidak pernah dipakai untuk akun karyawan sungguhan.
class DemoLoginController extends Controller
{
    public function store(Request $request, string $peran): RedirectResponse
    {
        $daftar = config('auth.demo_peran', []);

        abort_unless(config('auth.demo_login') && isset($daftar[$peran]), 404);

        $user = User::firstOrCreate(['username' => "demo.{$peran}"], [
            'name' => "Demo {$daftar[$peran]}",
            'kategori' => $peran,
            'password' => Str::random(40), // tidak dipakai; masuk lewat tombol demo
            'aktif' => true,
            'wajib_ganti_password' => false,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['login_terakhir_pada' => now()])->save();

        return redirect()->intended('/home');
    }
}
