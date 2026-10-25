<?php

namespace App\Http\Controllers\Mobile;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Mobile\ApiError;
use App\Support\Mobile\TokenPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Login aplikasi mobile: username + password + identitas HP => token Bearer per perangkat.
// Akun sales motoris dan koordinator; akun lain ditolak role_not_supported.
class AuthController extends MobileController
{
    public const PERAN_DIDUKUNG = ['sales', 'koordinator'];

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
            'device_id' => ['required', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        $kunci = 'mobile-login|'.mb_strtolower(trim($data['username']));

        if (RateLimiter::tooManyAttempts($kunci, config('mobile.batas_login_per_menit'))) {
            $detik = RateLimiter::availableIn($kunci);

            throw new ApiError('too_many_attempts', "Terlalu banyak percobaan login. Coba lagi dalam $detik detik.", 429, [], ['Retry-After' => $detik]);
        }

        $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower(trim($data['username']))])->first();

        if (! $user || ! $user->aktif || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($kunci, 60);

            throw new ApiError('invalid_credentials', 'Username atau password salah.', 401);
        }

        RateLimiter::clear($kunci);

        if (! in_array($user->kategori, self::PERAN_DIDUKUNG, true) || ! Akses::bolehMobile($user)) {
            throw new ApiError('role_not_supported', 'Aplikasi mobile saat ini untuk sales motoris dan koordinator.', 403);
        }

        if ($user->perlu_keputusan) {
            throw new ApiError('account_under_review', 'Akun sedang diperiksa admin. Hubungi admin.', 403);
        }

        if (! $user->karyawan_id) {
            throw new ApiError('no_employee_link', 'Akun belum terhubung ke data karyawan. Hubungi admin.', 403);
        }

        $token = TokenPerangkat::buat($user, $data);
        $user->forceFill(['login_terakhir_pada' => now()])->save();

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'must_change_password' => (bool) $user->wajib_ganti_password,
            'user' => ProfilController::profil($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        TokenPerangkat::cabut(TokenPerangkat::token($request) ?? throw ApiError::tidakAda(), 'logout');

        return $this->ok(['logged_out' => true]);
    }

    public function gantiPassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $wajib = (bool) $user->wajib_ganti_password;

        $request->validate([
            'current_password' => [$wajib ? 'nullable' : 'required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed', Rule::notIn([$user->username, mb_strtolower((string) $user->username)])],
        ], [
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.not_in' => 'Password tidak boleh sama dengan username.',
            'current_password.required' => 'Password lama wajib diisi.',
        ]);

        if (! $wajib && ! Hash::check((string) $request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password lama salah.']);
        }

        if (Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Password baru harus berbeda dari password sekarang.']);
        }

        $user->forceFill(['password' => $request->input('password'), 'wajib_ganti_password' => false])->save();

        return $this->ok(['password_changed' => true]);
    }
}
