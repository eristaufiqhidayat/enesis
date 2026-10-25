<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\JamLokal;
use App\Support\Menu\SumberTabel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Sesi Login: sesi aplikasi HP yang masih aktif (mobile_tokens belum dicabut), satu akun satu HP. */
class SumberSesiLogin implements SumberTabel
{
    public function tabelUtama(): string
    {
        return 'mobile_tokens';
    }

    public function query(?User $user): Builder
    {
        $q = DB::table('mobile_tokens as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->join('mk_karyawan as k', 'k.id', '=', 'u.karyawan_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->whereNull('m.revoked_at')
            ->orderByDesc('m.last_used_at')->orderByDesc('m.id')
            ->select([
                'm.id', 'u.username as username_nickname', 'u.kategori', 'd.nama as depo', 'k.nama as nama_karyawan', 'k.id as id_karyawan',
                'ko.nama as nama_koordinator', 'm.device_id', 'm.device_model', 'm.app_version', 'm.created_at as login_pada', 'm.last_used_at as terakhir_aktif',
            ]);

        return Akses::batasiKaryawan($q, $user);
    }

    public function ubahBaris(object $baris): object
    {
        $baris->kategori = ucfirst((string) $baris->kategori);
        $baris->device_token = trim(($baris->device_model ? $baris->device_model.' · ' : '').$baris->device_id.($baris->app_version ? ' · v'.$baris->app_version : ''));
        $baris->login_pada = JamLokal::format($baris->login_pada, 'Y-m-d H:i:s');
        $baris->terakhir_aktif = JamLokal::format($baris->terakhir_aktif, 'Y-m-d H:i:s');
        unset($baris->device_id, $baris->device_model, $baris->app_version);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['u.username', 'k.nama', 'd.nama', 'ko.nama', 'm.device_id', 'm.device_model'];
    }
}
