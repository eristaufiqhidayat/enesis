<?php

namespace App\Support\Mobile;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Token API per HP (guard "mobile"). Server hanya menyimpan sha256 token; satu akun hanya
// aktif di satu HP: login di HP lain mencabut token lama (HP lama mendapat 401 device_replaced).
class TokenPerangkat
{
    public const ATRIBUT_TOKEN = 'mobile_token';

    public const ATRIBUT_DICABUT = 'mobile_token_dicabut';

    /** Buat token baru untuk perangkat; token aktif lain milik user dicabut. */
    public static function buat(User $user, array $perangkat): string
    {
        $polos = 'enm_'.Str::random(60);
        $sekarang = now();

        DB::transaction(function () use ($user, $perangkat, $polos, $sekarang) {
            DB::table('mobile_tokens')->where('user_id', $user->id)->whereNull('revoked_at')
                ->where('device_id', '!=', $perangkat['device_id'])
                ->update(['revoked_at' => $sekarang, 'revoked_reason' => 'device_replaced', 'updated_at' => $sekarang]);

            DB::table('mobile_tokens')->where('user_id', $user->id)->whereNull('revoked_at')
                ->update(['revoked_at' => $sekarang, 'revoked_reason' => 'relogin', 'updated_at' => $sekarang]);

            DB::table('mobile_tokens')->insert([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $polos),
                'device_id' => $perangkat['device_id'],
                'device_model' => $perangkat['device_model'] ?? null,
                'app_version' => $perangkat['app_version'] ?? null,
                'last_used_at' => $sekarang,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);
        });

        return $polos;
    }

    /** Pemecah guard "mobile" (Auth::viaRequest). */
    public static function user(Request $request): ?User
    {
        $polos = $request->bearerToken();

        if (! $polos) {
            return null;
        }

        $token = DB::table('mobile_tokens')->where('token_hash', hash('sha256', $polos))->first();

        if (! $token) {
            return null;
        }

        $sekarang = now();

        if (! $token->revoked_at && $token->last_used_at && $sekarang->copy()->subDays(config('mobile.token_idle_hari'))->greaterThan($token->last_used_at)) {
            DB::table('mobile_tokens')->where('id', $token->id)->update(['revoked_at' => $sekarang, 'revoked_reason' => 'idle', 'updated_at' => $sekarang]);
            $token->revoked_at = $sekarang;
            $token->revoked_reason = 'idle';
        }

        if ($token->revoked_at) {
            $request->attributes->set(self::ATRIBUT_DICABUT, $token->revoked_reason);

            return null;
        }

        $user = User::find($token->user_id);

        if (! $user || ! $user->aktif) {
            $request->attributes->set(self::ATRIBUT_DICABUT, 'account_inactive');

            return null;
        }

        // Catat pemakaian paling sering sekali per menit supaya tidak menulis tiap permintaan.
        $versi = $request->header('X-App-Version');

        if (! $token->last_used_at || $sekarang->copy()->subMinute()->greaterThan($token->last_used_at) || ($versi && $versi !== $token->app_version)) {
            DB::table('mobile_tokens')->where('id', $token->id)->update(array_filter([
                'last_used_at' => $sekarang,
                'app_version' => $versi,
                'updated_at' => $sekarang,
            ]));
        }

        $request->attributes->set(self::ATRIBUT_TOKEN, $token);

        return $user;
    }

    /** Token perangkat dari permintaan ini (diisi guard; dicari ulang bila guard memakai user tersimpan). */
    public static function token(Request $request): ?object
    {
        if (! $request->attributes->has(self::ATRIBUT_TOKEN) && ($polos = $request->bearerToken())) {
            $request->attributes->set(self::ATRIBUT_TOKEN, DB::table('mobile_tokens')
                ->where('token_hash', hash('sha256', $polos))->whereNull('revoked_at')->first());
        }

        return $request->attributes->get(self::ATRIBUT_TOKEN);
    }

    public static function cabut(object $token, string $alasan): void
    {
        DB::table('mobile_tokens')->where('id', $token->id)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => $alasan, 'updated_at' => now()]);
    }

    public static function pesanDicabut(?string $alasan): array
    {
        return match ($alasan) {
            'device_replaced' => ['device_replaced', 'Akun Anda masuk di HP lain. Silakan login ulang.'],
            'idle' => ['token_expired', 'Sesi berakhir karena lama tidak dipakai. Silakan login ulang.'],
            'account_inactive' => ['account_inactive', 'Akun tidak aktif. Hubungi admin.'],
            'admin' => ['token_revoked', 'Sesi dicabut admin. Silakan login ulang.'],
            null => ['unauthenticated', 'Silakan login.'],
            default => ['token_revoked', 'Sesi sudah berakhir. Silakan login ulang.'],
        };
    }
}
