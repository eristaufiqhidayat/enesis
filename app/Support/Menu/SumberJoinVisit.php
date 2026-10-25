<?php

namespace App\Support\Menu;

use App\Models\User;
use App\Support\Akses\Akses;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Join visit (trx_join_visit): dari aplikasi HP koordinator dan riwayat aplikasi lama
 * (enesis:migrasi-transaksi join_visit). Baris dibatasi cakupan role lewat koordinatornya.
 */
class SumberJoinVisit implements SumberTabel
{
    public function tabelUtama(): string
    {
        return 'trx_join_visit';
    }

    public function query(?User $user): Builder
    {
        $query = DB::table('trx_join_visit as j')
            ->leftJoin('mk_karyawan as k', 'k.id', '=', 'j.koordinator_id')
            ->leftJoin('mk_karyawan as s', 's.id', '=', 'j.sales_id')
            ->leftJoin('mk_outlet as o', 'o.id', '=', 'j.outlet_id')
            ->leftJoin('mk_area as a', 'a.id', '=', 'j.area_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'j.depo_id')
            ->select([
                'j.id', 'j.tanggal', 'k.nama as koordinator', 's.nama as sales',
                'o.kode as kode_outlet', 'o.nama as outlet', 'a.nama as area', 'd.nama as depo',
                'j.mulai_at as mulai', 'j.selesai_at as selesai', 'j.durasi_detik', 'j.jarak_m',
                'j.catatan', 'j.sumber',
                // Untuk tombol foto, tidak tampil sebagai kolom.
                'j.foto_id',
            ])
            ->orderByDesc('j.tanggal')->orderByDesc('j.mulai_at')->orderByDesc('j.id');

        return Akses::batasiKaryawan($query, $user);
    }

    public function ubahBaris(object $baris): object
    {
        $baris->tanggal = substr((string) $baris->tanggal, 0, 10);
        $baris->mulai = JamLokal::format($baris->mulai, 'H:i');
        $baris->selesai = $baris->selesai ? JamLokal::format($baris->selesai, 'H:i') : 'Berlangsung';
        $baris->durasi = $baris->durasi_detik !== null ? (int) round($baris->durasi_detik / 60).' menit' : null;
        $baris->sumber = $baris->sumber === 'lama' ? 'Aplikasi lama' : 'Aplikasi HP';
        unset($baris->durasi_detik);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['k.nama', 's.nama', 'o.kode', 'o.nama', 'a.nama', 'd.nama', 'j.catatan'];
    }
}
