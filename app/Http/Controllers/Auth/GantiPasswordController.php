<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

// Ganti password. Wajib setelah login pertama dengan password awal dari admin.
class GantiPasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/GantiPassword', ['wajib' => (bool) $request->user()->wajib_ganti_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            // Password awal dari admin baru saja dipakai login, jadi tidak perlu diminta lagi.
            'password_lama' => $user->wajib_ganti_password ? ['nullable'] : ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'not_in:'.$user->username],
        ], [
            'password_lama.current_password' => 'Password lama salah.',
            'password.not_in' => 'Password tidak boleh sama dengan username.',
        ], [
            'password_lama' => 'password lama',
            'password' => 'password baru',
        ]);

        if (Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Password baru harus berbeda dari password sekarang.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'wajib_ganti_password' => false,
        ])->save();

        return redirect()->intended('/home')->with('status', 'Password berhasil diganti.');
    }
}
