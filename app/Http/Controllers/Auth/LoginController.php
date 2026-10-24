<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// Login session berbasis username (akun hasil migrasi Marketama umumnya tanpa email).
class LoginController extends Controller
{
    private const MAKS_PERCOBAAN = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'username', 'password' => 'password']);

        $kunci = Str::lower($data['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            throw ValidationException::withMessages([
                'username' => 'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.',
            ]);
        }

        // Username tidak membedakan huruf besar/kecil (sama seperti aturan migrasi akun).
        $user = User::whereRaw('lower(username) = ?', [Str::lower($data['username'])])->first();

        if (! $user || ! $user->aktif || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($kunci, 60);

            throw ValidationException::withMessages(['username' => 'Username atau password salah.']);
        }

        RateLimiter::clear($kunci);
        Auth::login($user, $request->boolean('ingat'));
        $request->session()->regenerate();
        $user->forceFill(['login_terakhir_pada' => now()])->save();

        return redirect()->intended('/home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
